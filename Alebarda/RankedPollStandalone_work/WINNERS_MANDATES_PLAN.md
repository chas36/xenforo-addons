# План внедрения: Победители и распределение мандатов

## Обзор

Добавление функционала выбора количества победителей и распределения мандатов/мест для голосований методом Шульце.

**Режимы работы:**
1. **single** - один победитель (по умолчанию, текущее поведение)
2. **top_n** - топ-N победителей из ранжирования
3. **seat_allocation** - распределение фиксированного количества мандатов методом Сент-Лагю

## 1. Изменения схемы базы данных

### Новые столбцы в таблице `xf_alebarda_ranked_poll`

```sql
ALTER TABLE xf_alebarda_ranked_poll
ADD COLUMN winner_mode ENUM('single', 'top_n', 'seat_allocation')
    NOT NULL DEFAULT 'single'
    AFTER poll_status,
ADD COLUMN winner_count TINYINT UNSIGNED
    NOT NULL DEFAULT 1
    AFTER winner_mode
    COMMENT 'Количество победителей или мандатов',
ADD COLUMN allocation_results MEDIUMTEXT NULL
    AFTER cached_results
    COMMENT 'JSON результаты распределения мандатов';
```

### Миграция в Setup.php

```php
public function upgrade2010370Step1()
{
    $this->schemaManager()->alterTable('xf_alebarda_ranked_poll', function(Alter $table) {
        $table->addColumn('winner_mode', 'enum')
            ->values(['single', 'top_n', 'seat_allocation'])
            ->setDefault('single')
            ->after('poll_status');

        $table->addColumn('winner_count', 'tinyint')
            ->unsigned()
            ->setDefault(1)
            ->after('winner_mode');

        $table->addColumn('allocation_results', 'mediumtext')
            ->nullable()
            ->after('cached_results');
    });
}
```

## 2. Изменения Entity/Poll.php

### Добавить поля в структуру

```php
public static function getStructure(Structure $structure)
{
    $structure->columns = [
        // ... существующие поля
        'winner_mode' => ['type' => self::STR, 'default' => 'single',
            'allowedValues' => ['single', 'top_n', 'seat_allocation']],
        'winner_count' => ['type' => self::UINT, 'default' => 1, 'min' => 1, 'max' => 100],
        'allocation_results' => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null],
    ];

    return $structure;
}
```

### Валидация в _preSave()

```php
protected function _preSave()
{
    parent::_preSave();

    // Валидация winner_count в зависимости от режима
    if ($this->winner_mode === 'single') {
        $this->winner_count = 1;
    } elseif ($this->winner_mode === 'top_n') {
        // Проверка, что winner_count не превышает количество опций
        $optionCount = count($this->Options);
        if ($this->winner_count > $optionCount) {
            $this->error(\XF::phrase('alebarda_rankedpoll_winner_count_exceeds_options'));
        }
    } elseif ($this->winner_mode === 'seat_allocation') {
        // Минимум 1 мандат
        if ($this->winner_count < 1) {
            $this->error(\XF::phrase('alebarda_rankedpoll_seat_count_minimum'));
        }
    }
}
```

### Вспомогательные методы

```php
/**
 * Получить результаты распределения мандатов
 */
public function getAllocationResults()
{
    return $this->allocation_results ?: [];
}

/**
 * Установить результаты распределения мандатов
 */
public function setAllocationResults(array $results)
{
    $this->allocation_results = $results;
}

/**
 * Проверка, используется ли режим множественных победителей
 */
public function hasMultipleWinners()
{
    return in_array($this->winner_mode, ['top_n', 'seat_allocation']);
}

/**
 * Получить массив ID победителей
 */
public function getWinnerIds()
{
    $results = $this->getCachedResults();

    if ($this->winner_mode === 'single') {
        return $results['winner_id'] ? [$results['winner_id']] : [];
    } elseif ($this->winner_mode === 'top_n') {
        return array_slice($results['ranking'], 0, $this->winner_count);
    } elseif ($this->winner_mode === 'seat_allocation') {
        $allocation = $this->getAllocationResults();
        return array_keys($allocation['allocations'] ?? []);
    }

    return [];
}
```

## 3. Новый класс Voting/SainteLague.php

Реализация метода Сент-Лагю (Вебстера) для пропорционального распределения.

```php
<?php

namespace Alebarda\RankedPollStandalone\Voting;

class SainteLague
{
    /**
     * Распределить места методом Сент-Лагю
     *
     * @param array $votes Массив голосов: [user_id => [option_id => rank, ...], ...]
     * @param array $ranking Финальное ранжирование Шульце
     * @param int $totalSeats Общее количество мест для распределения
     * @return array ['allocations' => [option_id => seats], 'details' => [...]]
     */
    public function allocateSeats(array $votes, array $ranking, $totalSeats)
    {
        // 1. Подсчет голосов первого выбора для каждой опции
        $firstChoiceVotes = $this->countFirstChoiceVotes($votes, $ranking);

        // Инициализация: никто ещё не получил места
        $seatsWon = [];
        foreach ($ranking as $optionId) {
            $seatsWon[$optionId] = 0;
        }

        // Детализация для отладки/отображения
        $allocationDetails = [];

        // 2. Распределение мест по одному
        for ($seat = 1; $seat <= $totalSeats; $seat++) {
            $maxQuotient = -1;
            $winnerOptionId = null;

            // Вычислить частное для каждой опции
            foreach ($firstChoiceVotes as $optionId => $voteCount) {
                // Делители Сент-Лагю: 1, 3, 5, 7, 9, ...
                $divisor = (2 * $seatsWon[$optionId]) + 1;
                $quotient = $voteCount / $divisor;

                if ($quotient > $maxQuotient) {
                    $maxQuotient = $quotient;
                    $winnerOptionId = $optionId;
                }
            }

            // Присвоить место победителю этого раунда
            if ($winnerOptionId !== null) {
                $seatsWon[$winnerOptionId]++;

                $allocationDetails[] = [
                    'seat' => $seat,
                    'option_id' => $winnerOptionId,
                    'votes' => $firstChoiceVotes[$winnerOptionId],
                    'divisor' => (2 * ($seatsWon[$winnerOptionId] - 1)) + 1,
                    'quotient' => $maxQuotient,
                    'total_seats_now' => $seatsWon[$winnerOptionId]
                ];
            }
        }

        // 3. Отфильтровать опции без мест
        $allocations = array_filter($seatsWon, function($seats) {
            return $seats > 0;
        });

        return [
            'allocations' => $allocations,
            'details' => $allocationDetails,
            'first_choice_votes' => $firstChoiceVotes
        ];
    }

    /**
     * Подсчитать голоса первого выбора
     */
    protected function countFirstChoiceVotes(array $votes, array $ranking)
    {
        $counts = [];

        foreach ($ranking as $optionId) {
            $counts[$optionId] = 0;
        }

        foreach ($votes as $userId => $userVote) {
            // Найти опцию с наименьшим рангом (первый выбор)
            $minRank = PHP_INT_MAX;
            $firstChoice = null;

            foreach ($userVote as $optionId => $rank) {
                if ($rank < $minRank) {
                    $minRank = $rank;
                    $firstChoice = $optionId;
                }
            }

            if ($firstChoice !== null) {
                $counts[$firstChoice]++;
            }
        }

        return $counts;
    }
}
```

## 4. Изменения Repository/Poll.php

### Модификация метода calculateResults()

```php
public function calculateResults(\Alebarda\RankedPollStandalone\Entity\Poll $poll)
{
    // Получить все голоса
    $votes = $this->db()->fetchAllKeyed("
        SELECT user_id, rankings
        FROM xf_alebarda_ranked_vote
        WHERE poll_id = ?
    ", 'user_id', $poll->poll_id);

    // Десериализовать ранжирование
    foreach ($votes as &$vote) {
        $vote = json_decode($vote['rankings'], true);
    }

    // Выполнить расчёт методом Шульце
    $schulze = new \Alebarda\RankedPollStandalone\Voting\Schulze();
    $results = $schulze->calculate($votes, array_keys($poll->Options->toArray()));

    // Расширенная обработка в зависимости от режима
    switch ($poll->winner_mode) {
        case 'single':
            // Текущее поведение: один победитель
            $results['winners'] = $results['winner_id'] ? [$results['winner_id']] : [];
            break;

        case 'top_n':
            // Топ-N из ранжирования
            $results['winners'] = array_slice($results['ranking'], 0, $poll->winner_count);
            break;

        case 'seat_allocation':
            // Распределение мандатов методом Сент-Лагю
            $sainteLague = new \Alebarda\RankedPollStandalone\Voting\SainteLague();
            $allocation = $sainteLague->allocateSeats(
                $votes,
                $results['ranking'],
                $poll->winner_count
            );

            $results['allocation'] = $allocation;
            $results['winners'] = array_keys($allocation['allocations']);

            // Сохранить детальные результаты распределения
            $poll->setAllocationResults($allocation);
            break;
    }

    // Кэшировать результаты
    $poll->setCachedResults($results);
    $poll->saveIfChanged();

    return $results;
}
```

## 5. Изменения админ-интерфейса

### Admin/Controller/RankedPoll.php - форма создания/редактирования

```php
protected function pollSaveProcess(\Alebarda\RankedPollStandalone\Entity\Poll $poll)
{
    $form = $this->formAction();

    $input = $this->filter([
        'title' => 'str',
        'description' => 'str',
        'poll_status' => 'str',
        'winner_mode' => 'str',
        'winner_count' => 'uint',
        'close_date' => 'datetime',
        'show_voter_list' => 'bool',
        // ... другие поля
    ]);

    $form->basicEntitySave($poll, $input);

    return $form;
}
```

### _output/templates/admin/rankedpoll_edit.html

Добавить секцию с настройками победителей:

```html
<xf:formrow label="{{ phrase('alebarda_rankedpoll_winner_mode') }}">
    <xf:radio name="winner_mode" value="{$poll.winner_mode}">
        <xf:option value="single" label="{{ phrase('alebarda_rankedpoll_winner_mode_single') }}">
            <xf:hint>{{ phrase('alebarda_rankedpoll_winner_mode_single_hint') }}</xf:hint>
        </xf:option>
        <xf:option value="top_n" label="{{ phrase('alebarda_rankedpoll_winner_mode_top_n') }}">
            <xf:hint>{{ phrase('alebarda_rankedpoll_winner_mode_top_n_hint') }}</xf:hint>
        </xf:option>
        <xf:option value="seat_allocation" label="{{ phrase('alebarda_rankedpoll_winner_mode_allocation') }}">
            <xf:hint>{{ phrase('alebarda_rankedpoll_winner_mode_allocation_hint') }}</xf:hint>
        </xf:option>
    </xf:radio>
</xf:formrow>

<xf:formrow label="{{ phrase('alebarda_rankedpoll_winner_count') }}"
           rowclass="js-winnerCountRow"
           explain="{{ phrase('alebarda_rankedpoll_winner_count_explain') }}">
    <xf:numberbox name="winner_count" value="{$poll.winner_count}" min="1" max="100" />
</xf:formrow>

<xf:js>
!function($) {
    var $form = $('#pollEditForm');
    var $winnerModeRadios = $form.find('input[name="winner_mode"]');
    var $winnerCountRow = $form.find('.js-winnerCountRow');

    function updateWinnerCountVisibility() {
        var mode = $winnerModeRadios.filter(':checked').val();
        if (mode === 'single') {
            $winnerCountRow.hide();
        } else {
            $winnerCountRow.show();
        }
    }

    $winnerModeRadios.on('change', updateWinnerCountVisibility);
    updateWinnerCountVisibility();
}(jQuery);
</xf:js>
```

## 6. Изменения публичных шаблонов

### _output/templates/public/rankedpoll_results.html

Обновить отображение победителей с иконками медалей:

```html
<xf:if is="$results.winners">
    <div class="block-row">
        <h3 class="block-minorHeader">
            <xf:if is="$poll.winner_mode == 'single'">
                {{ phrase('winner') }}
            <xf:elseif is="$poll.winner_mode == 'top_n'" />
                {{ phrase('alebarda_rankedpoll_top_winners', {'count': $poll.winner_count}) }}
            <xf:elseif is="$poll.winner_mode == 'seat_allocation'" />
                {{ phrase('alebarda_rankedpoll_seat_distribution', {'count': $poll.winner_count}) }}
            </xf:if>
        </h3>
    </div>

    <xf:foreach loop="$results.ranking" value="$optionId" key="$position">
        <xf:set var="$isWinner" value="{{ in_array($optionId, $results.winners) }}" />

        <div class="block-row block-row--separated {{ $isWinner ? 'block-row--highlighted' : '' }}">
            <div class="contentRow">
                <div class="contentRow-figure">
                    <xf:if is="$position == 0">
                        <i class="fa fa-medal fa-2x" style="color: gold;" title="{{ phrase('first_place') }}"></i>
                    <xf:elseif is="$position == 1" />
                        <i class="fa fa-medal fa-2x" style="color: silver;" title="{{ phrase('second_place') }}"></i>
                    <xf:elseif is="$position == 2" />
                        <i class="fa fa-medal fa-2x" style="color: #cd7f32;" title="{{ phrase('third_place') }}"></i>
                    <xf:else />
                        <strong style="font-size: 1.5em; color: #888;">#{$position + 1}</strong>
                    </xf:if>
                </div>
                <div class="contentRow-main">
                    <h4 class="contentRow-title">{$optionNames.{$optionId}}</h4>

                    <xf:if is="$poll.winner_mode == 'seat_allocation' AND $results.allocation.allocations.{$optionId}">
                        <div class="contentRow-snippet">
                            {{ phrase('alebarda_rankedpoll_seats_won', {
                                'seats': $results.allocation.allocations.{$optionId},
                                'total': $poll.winner_count
                            }) }}
                        </div>
                    </xf:if>
                </div>
            </div>
        </div>
    </xf:foreach>
</xf:if>
```

### Детализация распределения мандатов (опционально)

```html
<xf:if is="$poll.winner_mode == 'seat_allocation' AND $results.allocation.details">
    <div class="block-row">
        <h3 class="block-minorHeader">{{ phrase('alebarda_rankedpoll_allocation_details') }}</h3>
    </div>

    <div class="block-row">
        <table class="dataList dataList--compact">
            <thead>
                <tr>
                    <th>{{ phrase('alebarda_rankedpoll_seat_number') }}</th>
                    <th>{{ phrase('option') }}</th>
                    <th>{{ phrase('alebarda_rankedpoll_first_choice_votes') }}</th>
                    <th>{{ phrase('alebarda_rankedpoll_divisor') }}</th>
                    <th>{{ phrase('alebarda_rankedpoll_quotient') }}</th>
                </tr>
            </thead>
            <tbody>
                <xf:foreach loop="$results.allocation.details" value="$detail">
                    <tr>
                        <td>#{$detail.seat}</td>
                        <td>{$optionNames.{$detail.option_id}}</td>
                        <td>{$detail.votes}</td>
                        <td>{$detail.divisor}</td>
                        <td>{{ number_format($detail.quotient, 2) }}</td>
                    </tr>
                </xf:foreach>
            </tbody>
        </table>
    </div>
</xf:if>
```

## 7. Новые фразы

Создать следующие файлы в `_output/phrases/`:

- `alebarda_rankedpoll_winner_mode.txt`: "Режим определения победителей"
- `alebarda_rankedpoll_winner_mode_single.txt`: "Один победитель"
- `alebarda_rankedpoll_winner_mode_single_hint.txt`: "Стандартный режим: определяется единственный победитель по методу Шульце"
- `alebarda_rankedpoll_winner_mode_top_n.txt`: "Топ-N победителей"
- `alebarda_rankedpoll_winner_mode_top_n_hint.txt`: "Выбрать несколько победителей из финального ранжирования"
- `alebarda_rankedpoll_winner_mode_allocation.txt`: "Распределение мандатов"
- `alebarda_rankedpoll_winner_mode_allocation_hint.txt`: "Пропорциональное распределение мест методом Сент-Лагю на основе голосов первого выбора"
- `alebarda_rankedpoll_winner_count.txt`: "Количество"
- `alebarda_rankedpoll_winner_count_explain.txt`: "Для режима 'Топ-N': количество победителей. Для режима 'Распределение мандатов': общее количество мест для распределения."
- `alebarda_rankedpoll_winner_count_exceeds_options.txt`: "Количество победителей не может превышать количество вариантов в голосовании."
- `alebarda_rankedpoll_seat_count_minimum.txt`: "Количество мандатов должно быть не менее 1."
- `alebarda_rankedpoll_top_winners.txt`: "Топ-{count} победителей"
- `alebarda_rankedpoll_seat_distribution.txt`: "Распределение {count} мандатов"
- `alebarda_rankedpoll_seats_won.txt`: "Получено мест: {seats} из {total}"
- `alebarda_rankedpoll_allocation_details.txt`: "Детали распределения мандатов"
- `alebarda_rankedpoll_seat_number.txt`: "№ места"
- `alebarda_rankedpoll_first_choice_votes.txt`: "Голоса 1-го выбора"
- `alebarda_rankedpoll_divisor.txt`: "Делитель"
- `alebarda_rankedpoll_quotient.txt`: "Частное"

## 8. Обратная совместимость

### Значения по умолчанию

Для существующих голосований:
- `winner_mode` = 'single' (по умолчанию в ALTER TABLE)
- `winner_count` = 1 (по умолчанию в ALTER TABLE)
- `allocation_results` = NULL

### Поддержка старого API

Метод `getCachedResults()` продолжает возвращать `winner_id` для режима 'single', что сохраняет совместимость с существующим кодом отображения.

### Миграция данных

Не требуется - все существующие голосования автоматически получат режим 'single' с одним победителем.

## 9. Последовательность внедрения

### Шаг 1: База данных и Entity
1. Добавить метод `upgrade2010370Step1()` в `Setup.php`
2. Обновить `Entity/Poll.php`: добавить поля, валидацию, вспомогательные методы
3. Обновить версию аддона в `addon.json` до 2.1.3

### Шаг 2: Алгоритм Сент-Лагю
1. Создать `Voting/SainteLague.php`
2. Добавить unit-тесты для алгоритма (опционально)

### Шаг 3: Repository и расчёт результатов
1. Модифицировать `Repository/Poll.php::calculateResults()`
2. Протестировать все три режима на тестовых данных

### Шаг 4: Админ-интерфейс
1. Обновить `Admin/Controller/RankedPoll.php`
2. Создать/обновить `_output/templates/admin/rankedpoll_edit.html`
3. Добавить JavaScript для управления видимостью поля `winner_count`

### Шаг 5: Публичные шаблоны
1. Обновить `_output/templates/public/rankedpoll_results.html`
2. Добавить отображение медалей
3. Добавить секцию детализации распределения мандатов

### Шаг 6: Фразы и локализация
1. Создать все необходимые файлы фраз в `_output/phrases/`
2. Экспортировать через XenForo dev mode

### Шаг 7: Тестирование
1. Создать тестовое голосование в режиме 'single' - проверить совместимость
2. Создать тестовое голосование в режиме 'top_n' с 3 победителями
3. Создать тестовое голосование в режиме 'seat_allocation' с 10 мандатами
4. Проверить корректность расчётов вручную

### Шаг 8: Документация
1. Обновить README или документацию аддона
2. Добавить примеры использования каждого режима

## 10. Тестовые сценарии

### Тест 1: Режим 'single' (обратная совместимость)
- Создать голосование с 4 опциями
- Режим: single
- Добавить 10 голосов
- **Ожидается**: один победитель, отображение с золотой медалью

### Тест 2: Режим 'top_n' с 3 победителями
- Создать голосование с 5 опциями
- Режим: top_n, winner_count = 3
- Добавить 20 голосов
- **Ожидается**: топ-3 получают медали (золото, серебро, бронза), остальные - нумерацию

### Тест 3: Режим 'seat_allocation' с 10 мандатами
- Создать голосование с 4 опциями ("партиями")
- Режим: seat_allocation, winner_count = 10
- Добавить 100 голосов с разными предпочтениями
- **Ожидается**:
  - Распределение 10 мандатов между партиями
  - Таблица детализации с делителями и частными
  - Сумма мандатов = 10

### Тест 4: Граничные случаи
- **Случай A**: winner_count > количество опций в режиме top_n
  - **Ожидается**: ошибка валидации при сохранении
- **Случай B**: winner_count = 0 в режиме seat_allocation
  - **Ожидается**: ошибка валидации
- **Случай C**: Нет голосов
  - **Ожидается**: корректная обработка без падения

### Тест 5: Сравнение с ручным расчётом
Для голосования с известным распределением:
- 100 голосов: Партия A (45), Партия B (30), Партия C (15), Партия D (10)
- 7 мандатов для распределения
- **Ожидаемое распределение Сент-Лагю**: A=3, B=2, C=1, D=1

## Примечания

### Метод Сент-Лагю vs Д'Ондта
Выбран метод Сент-Лагю, так как он более справедлив для малых партий. Делители:
- **Сент-Лагю**: 1, 3, 5, 7, 9, ... (нечётные числа)
- **Д'Ондт**: 1, 2, 3, 4, 5, ... (натуральные числа)

Метод Д'Ондта можно добавить позже как опциональный параметр.

### Производительность
Для голосований с большим количеством мандатов (>100) может потребоваться оптимизация алгоритма распределения. Текущая реализация имеет сложность O(seats × options).

### Возможные расширения
1. Выбор метода распределения (Сент-Лагю / Д'Ондт)
2. Минимальный порог для получения мандатов (например, 5%)
3. Экспорт результатов распределения в CSV/Excel
4. Графическая визуализация распределения мандатов
