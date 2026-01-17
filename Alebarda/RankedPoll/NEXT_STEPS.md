# Следующие шаги для завершения RankedPoll

## ✅ Что уже работает (безопасные расширения)

### Backend расширения:
- **XF/Entity/Poll.php** - добавлены методы для ranked polls ✅
  - `isRankedPoll()` - проверка типа опроса
  - `getUserRankedVotes()` - получение голосов пользователя
  - `getRankedMetadata()` - получение настроек
  - Lifecycle hooks для очистки данных

- **XF/Repository/PollRepository.php** - перехват голосования ✅
  - `voteOnPoll()` - автоматически определяет ranked/standard
  - `voteOnRankedPoll()` - логика ranked голосования
  - `getRankedVotes()` - получение всех голосов
  - `getRankedVoters()` - список голосовавших

- **XF/Service/Poll/Creator.php** - создание ranked polls через API ✅
  - `setRankedVoting()` - включить ranked mode
  - `setRankedResultsVisibility()` - настройка видимости
  - Автоматическое сохранение metadata

- **Voting/Schulze.php** - алгоритм подсчёта ✅
  - Полная реализация метода Шульца
  - Pairwise matrix + Floyd-Warshall
  - Определение победителя

### Сайт работает:
- Форум не сломан ✅
- Расширения загружены на сервер ✅
- PHP синтаксис корректный ✅

---

## 🔧 Что нужно доделать

### 1. Проверить наличие таблиц БД

Подключиться по SSH и проверить:
```bash
cd /var/www/u0513784/data/www/beta.politsim.ru
php cmd.php xf-db:query "SHOW TABLES LIKE 'xf_%ranked%'"
```

Должны существовать:
- `xf_poll_ranked_vote` - голоса пользователей
- `xf_alebarda_ranked_poll_metadata` - настройки опросов

Если таблиц нет - нужно создать через Setup.php или миграцию.

---

### 2. Создать контроллер для результатов

**Файл**: `Pub/Controller/RankedPoll.php`

```php
<?php
namespace Alebarda\RankedPoll\Pub\Controller;

use XF\Mvc\ParameterBag;

class RankedPoll extends \XF\Pub\Controller\AbstractController
{
    /**
     * Показать результаты ranked poll
     */
    public function actionResults(ParameterBag $params)
    {
        $poll = $this->assertViewablePoll($params->poll_id);

        if (!$poll->isRankedPoll())
        {
            return $this->error('Not a ranked poll');
        }

        if (!$poll->canViewRankedResults($error))
        {
            return $this->noPermission($error);
        }

        // Получить голоса
        /** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
        $pollRepo = $this->repository('XF:PollRepository');
        $votes = $pollRepo->getRankedVotes($poll);

        // Подсчитать результаты
        $schulze = new \Alebarda\RankedPoll\Voting\Schulze();
        $candidates = array_keys($poll->responses);
        $results = $schulze->calculateWinner($votes, $candidates);

        // Добавить имена кандидатов
        $candidateNames = [];
        foreach ($poll->responses as $response)
        {
            $candidateNames[$response->poll_response_id] = $response->response;
        }

        return $this->view('Alebarda\RankedPoll:Results', 'poll_results_ranked', [
            'poll' => $poll,
            'results' => $results,
            'candidateNames' => $candidateNames,
            'voterCount' => count($votes)
        ]);
    }

    /**
     * Показать список голосовавших
     */
    public function actionVoters(ParameterBag $params)
    {
        $poll = $this->assertViewablePoll($params->poll_id);

        if (!$poll->isRankedPoll())
        {
            return $this->error('Not a ranked poll');
        }

        $metadata = $poll->getRankedMetadata();
        if (!$metadata || !$metadata['show_voter_list'])
        {
            return $this->noPermission();
        }

        /** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
        $pollRepo = $this->repository('XF:PollRepository');
        $voters = $pollRepo->getRankedVoters($poll, 100);

        return $this->view('Alebarda\RankedPoll:Voters', 'poll_voters_ranked', [
            'poll' => $poll,
            'voters' => $voters
        ]);
    }

    protected function assertViewablePoll($pollId)
    {
        $poll = \XF::em()->find('XF:Poll', $pollId);
        if (!$poll)
        {
            throw $this->exception($this->notFound(\XF::phrase('requested_poll_not_found')));
        }

        $content = $poll->Content;
        if (!$content || !$content->canView($error))
        {
            throw $this->exception($this->noPermission($error));
        }

        return $poll;
    }
}
```

---

### 3. Зарегистрировать роуты

**Файл**: `_output/routes/public/ranked_poll_results.json`

```json
{
    "route_type": "public",
    "route_prefix": "ranked-polls",
    "sub_name": "results",
    "format": ":int<poll_id>",
    "build_class": "",
    "build_method": "",
    "controller": "Alebarda\\RankedPoll:RankedPoll",
    "context": "",
    "action_prefix": "Results"
}
```

**Файл**: `_output/routes/public/ranked_poll_voters.json`

```json
{
    "route_type": "public",
    "route_prefix": "ranked-polls",
    "sub_name": "voters",
    "format": ":int<poll_id>",
    "build_class": "",
    "build_method": "",
    "controller": "Alebarda\\RankedPoll:RankedPoll",
    "context": "",
    "action_prefix": "Voters"
}
```

---

### 4. Создать шаблон результатов

**Файл**: `_output/templates/public/poll_results_ranked.html`

```html
<div class="block">
    <div class="block-container">
        <h2 class="block-header">
            {{ phrase('poll_results') }}: {{ $poll.question }}
        </h2>

        <div class="block-body">
            <!-- Победитель -->
            <xf:if is="$results.winner_id">
                <div class="pollResult pollResult--winner">
                    <div class="pollResult-response">
                        🏆 <strong>{{ phrase('winner') }}:</strong>
                        {{ $candidateNames[$results.winner_id] }}
                    </div>
                </div>

                <hr />

                <!-- Полное ранжирование -->
                <h3>{{ phrase('final_ranking') }}</h3>
                <ol class="pollResults">
                    <xf:foreach loop="$results.ranking" value="$candidateId" key="$position">
                        <li class="pollResult">
                            <div class="pollResult-response">
                                {{ $candidateNames[$candidateId] }}
                            </div>
                        </li>
                    </xf:foreach>
                </ol>

                <hr />

                <!-- Pairwise comparison matrix -->
                <xf:if is="$results.pairwise_matrix">
                    <h3>{{ phrase('alebarda_rankedpoll_pairwise_comparison') }}</h3>
                    <div class="block-body">
                        <p class="block-rowMessage">
                            {{ phrase('alebarda_rankedpoll_pairwise_explanation') }}
                        </p>

                        <table class="dataList">
                            <thead>
                                <tr>
                                    <th></th>
                                    <xf:foreach loop="$results.ranking" value="$candidateId">
                                        <th>{{ $candidateNames[$candidateId] }}</th>
                                    </xf:foreach>
                                </tr>
                            </thead>
                            <tbody>
                                <xf:foreach loop="$results.ranking" value="$rowId">
                                    <tr>
                                        <th>{{ $candidateNames[$rowId] }}</th>
                                        <xf:foreach loop="$results.ranking" value="$colId">
                                            <td style="text-align: center;">
                                                <xf:if is="$rowId == $colId">
                                                    -
                                                <xf:else />
                                                    {{ $results.pairwise_matrix[$rowId][$colId] }}
                                                </xf:if>
                                            </td>
                                        </xf:foreach>
                                    </tr>
                                </xf:foreach>
                            </tbody>
                        </table>
                    </div>
                </xf:if>

                <hr />

                <!-- Статистика -->
                <div class="block-rowMessage">
                    <strong>{{ phrase('total_votes') }}:</strong> {{ $voterCount }}
                </div>

            <xf:else />
                <div class="block-rowMessage">
                    {{ phrase('no_votes_cast') }}
                </div>
            </xf:if>

            <!-- Ссылка на список голосовавших -->
            <xf:if is="$poll.getRankedMetadata().show_voter_list">
                <div class="block-footer">
                    <a href="{{ link('ranked-polls/voters', $poll) }}">
                        {{ phrase('view_voters') }}
                    </a>
                </div>
            </xf:if>
        </div>
    </div>
</div>
```

---

### 5. Обновить poll_block_ranked.html

Добавить кнопку "Показать результаты":

```html
<xf:if is="$poll.canViewRankedResults()">
    <div class="pollFooter">
        <a href="{{ link('ranked-polls/results', $poll) }}" class="button button--link">
            📊 {{ phrase('view_results') }}
        </a>
    </div>
</xf:if>
```

---

### 6. Добавить phrases (фразы)

В `_output/phrases/`:

- `alebarda_rankedpoll_pairwise_comparison.txt`: "Pairwise Comparison Matrix"
- `alebarda_rankedpoll_pairwise_explanation.txt`: "Each cell shows how many voters preferred row candidate over column candidate"
- `final_ranking.txt`: "Final Ranking"
- `view_voters.txt`: "View Voters"

---

### 7. Тестирование

#### 7.1 Создать тестовый ranked poll

Через конверсионный скрипт:
```bash
php convert_poll_to_ranked.php 2
```

Или программно:
```php
$creator = \XF::service('XF:Poll\Creator', $thread);
$creator->setQuestion('Test ranked poll');
$creator->setResponses([
    'Option A',
    'Option B',
    'Option C'
]);
$creator->setRankedVoting([
    'results_visibility' => 'realtime'
]);
$poll = $creator->save();
```

#### 7.2 Проголосовать

Открыть опрос в браузере и проголосовать с несколькими аккаунтами.

#### 7.3 Проверить результаты

Открыть: `https://beta.politsim.ru/ranked-polls/results/POLL_ID`

Должны увидеть:
- Победителя
- Полное ранжирование
- Pairwise matrix
- Статистику

---

## 🎯 Roadmap

### Фаза 1: MVP (сейчас)
- [x] Repository расширение для голосования
- [x] Entity расширение с методами
- [x] Service расширение для создания
- [x] Schulze алгоритм
- [ ] Контроллер результатов
- [ ] Шаблон результатов
- [ ] Роуты

### Фаза 2: UI для создания
- [ ] Template modification в форме создания опроса
- [ ] Checkbox "Enable ranked voting"
- [ ] Форма настроек (видимость, группы доступа)

### Фаза 3: Расширенные функции
- [ ] Конвертация существующих опросов в ranked
- [ ] Экспорт результатов в CSV
- [ ] Графики и визуализация
- [ ] Уведомления автору опроса
- [ ] История изменения голосов

---

## 🔍 Команды для проверки

### Проверить расширения
```bash
php cmd.php xf-dev:class-extensions
```

### Проверить таблицы БД
```bash
php cmd.php xf-db:query "SHOW TABLES LIKE 'xf_%ranked%'"
```

### Очистить кэш
```bash
php cmd.php xf:rebuild-caches
```

### Проверить ошибки PHP
```bash
tail -f /var/www/u0513784/data/logs/error_log
```

---

## ✅ Готовы к следующему шагу?

Что хотите сделать сначала?
1. **Создать контроллер и шаблоны для просмотра результатов**
2. **Протестировать голосование** (создать тестовый опрос и проголосовать)
3. **Добавить UI для создания ranked polls** (checkbox в форме создания)
4. **Что-то другое?**
