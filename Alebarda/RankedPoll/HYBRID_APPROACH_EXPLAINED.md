# Гибридный подход - Детальное объяснение

## Суть идеи

**Используем XenForo для того, что он умеет хорошо + добавляем свою логику для ranked voting**

### Что берём от XenForo:
- ✅ Создание опроса (форма в теме)
- ✅ Хранение вариантов ответов (`xf_poll_response`)
- ✅ Привязка к темам (`thread_id`)
- ✅ Базовые разрешения (кто может создавать опросы)

### Что делаем сами:
- ✅ Голосование с ранжированием (наш контроллер)
- ✅ Расширенные настройки (время открытия/закрытия, видимость результатов)
- ✅ Подсчёт результатов (алгоритм Шульца)
- ✅ Отображение результатов (наши шаблоны)

---

## Пример работы (Step-by-step)

### Шаг 1: Создание опроса (используем XenForo)

Пользователь создаёт тему на форуме как обычно:

```
Заголовок темы: "Какой язык программирования выбрать для проекта?"

Текст сообщения: "Голосуйте за лучший вариант..."

☑️ Добавить опрос к теме
  Вопрос: "Выберите язык программирования"

  Варианты ответов:
  1. Python
  2. JavaScript
  3. Go
  4. Rust
  5. Java

  [Создать тему]
```

**Что происходит в БД**:
```sql
-- XenForo создаёт записи как обычно:
INSERT INTO xf_thread (thread_id, title, ...) VALUES (456, 'Какой язык...', ...);
INSERT INTO xf_poll (poll_id, content_type, content_id, ...) VALUES (123, 'thread', 456, ...);
INSERT INTO xf_poll_response (poll_response_id, poll_id, response) VALUES
  (1, 123, 'Python'),
  (2, 123, 'JavaScript'),
  (3, 123, 'Go'),
  (4, 123, 'Rust'),
  (5, 123, 'Java');
```

**Результат**: Создана тема с обычным опросом XenForo.

---

### Шаг 2: Конвертация в ranked poll (наша логика)

#### Вариант A: Автоматически (через checkbox при создании)

Добавляем checkbox в форму создания темы:
```
☑️ Добавить опрос к теме
  Вопрос: "Выберите язык программирования"

  Варианты ответов:
  1. Python
  2. JavaScript
  ...

  ☑️ Использовать ranked-choice voting (Schulze method)
      ↓
      Если отмечено → создаётся запись в нашей таблице
```

**Что происходит в БД**:
```sql
-- Наш аддон добавляет метаданные:
INSERT INTO xf_alebarda_ranked_poll_metadata (
  poll_id,
  is_ranked,
  results_visibility,
  allowed_user_groups,
  open_date,
  close_date,
  show_voter_list
) VALUES (
  123,                              -- poll_id (ссылка на xf_poll)
  1,                                -- это ranked poll
  'after_close',                    -- показывать результаты после закрытия
  '[2,3,4]',                        -- группы: Registered, Moderators, Admins
  NULL,                             -- открыт сразу
  '2025-12-31 23:59:59',           -- закрыть 31 декабря
  1                                 -- показывать список голосовавших
);
```

#### Вариант B: Через отдельную страницу настроек

После создания опроса, автор видит кнопку:
```
Опрос создан!

[⚙️ Настроить ranked voting]
```

Переходит на `/ranked-polls/123/configure`:
```
┌────────────────────────────────────────┐
│ Настройки ranked voting для опроса #123│
├────────────────────────────────────────┤
│ ☑️ Включить ranked-choice voting       │
│                                        │
│ Видимость результатов:                 │
│ ○ Всегда видны                         │
│ ○ После голосования                    │
│ ● После закрытия опроса                │
│ ○ Никогда (только админы)              │
│                                        │
│ Доступ к голосованию:                  │
│ ☑️ Registered Users                    │
│ ☑️ Premium Members                     │
│ ☑️ Moderators                          │
│                                        │
│ Время открытия:                        │
│ [____] (оставить пустым = сразу)       │
│                                        │
│ Время закрытия:                        │
│ [2025-12-31 23:59] 📅                 │
│                                        │
│ ☑️ Показывать список голосовавших      │
│                                        │
│         [Сохранить настройки]          │
└────────────────────────────────────────┘
```

После сохранения → создаётся та же запись в `xf_alebarda_ranked_poll_metadata`.

---

### Шаг 3: Просмотр опроса (наша логика для отображения)

Когда пользователь открывает тему, XenForo показывает опрос.

**Наш код проверяет**:
```php
// В расширении Entity: Alebarda\RankedPoll\XF\Entity\Poll

public function isRankedPoll()
{
    $metadata = $this->db()->fetchRow("
        SELECT * FROM xf_alebarda_ranked_poll_metadata
        WHERE poll_id = ?
    ", $this->poll_id);

    return $metadata && $metadata['is_ranked'];
}
```

**Если опрос ranked → используем наш шаблон**:

Через template modification в `poll_block`:
```html
<xf:if is="$poll.isRankedPoll()">
    <!-- Показываем наш интерфейс ранжирования -->
    <xf:include template="poll_block_ranked" />
<xf:else />
    <!-- Стандартный XenForo опрос -->
    <xf:include template="poll_macros::poll" />
</xf:if>
```

**Что видит пользователь**:
```
┌─────────────────────────────────────────────┐
│ Выберите язык программирования (Ranked Poll)│
├─────────────────────────────────────────────┤
│ Расставьте варианты по приоритету:          │
│                                             │
│ Python         [Rank: 1 ▼]                  │
│ JavaScript     [Rank: 2 ▼]                  │
│ Go             [Rank: - ▼]                  │
│ Rust           [Rank: 3 ▼]                  │
│ Java           [Rank: - ▼]                  │
│                                             │
│ Можно не ранжировать все варианты           │
│                                             │
│              [Проголосовать]                │
└─────────────────────────────────────────────┘
```

---

### Шаг 4: Голосование (наш контроллер)

**Форма отправляет POST на наш роут**:
```html
<form action="{{ link('ranked-polls/vote', $poll) }}" method="post">
    <input type="hidden" name="_xfToken" value="...">
    <select name="rankings[1]">...</select>  <!-- Python -->
    <select name="rankings[2]">...</select>  <!-- JavaScript -->
    ...
    <button type="submit">Проголосовать</button>
</form>
```

**Роут**: `POST /ranked-polls/123/vote`

**Контроллер**: `Alebarda\RankedPoll\Pub\Controller\Vote::actionVote()`

**Что делает контроллер**:
```php
public function actionVote(ParameterBag $params)
{
    // 1. Получить опрос из XenForo
    $poll = \XF::em()->find('XF:Poll', $params->poll_id);

    // 2. Проверить что это ranked poll
    if (!$poll->isRankedPoll()) {
        return $this->error('Это не ranked опрос');
    }

    // 3. Проверить права доступа (наша логика)
    $metadata = $poll->getRankedMetadata();

    // Проверка времени
    if ($metadata['open_date'] && time() < $metadata['open_date']) {
        return $this->error('Опрос ещё не открыт');
    }

    if ($metadata['close_date'] && time() > $metadata['close_date']) {
        return $this->error('Опрос уже закрыт');
    }

    // Проверка группы пользователя
    $allowedGroups = json_decode($metadata['allowed_user_groups']);
    $userGroups = \XF::visitor()->user_group_id;

    if (!in_array($userGroups, $allowedGroups)) {
        return $this->noPermission();
    }

    // 4. Получить ранжирование из формы
    $rankings = $this->filter('rankings', 'array-uint');
    // $rankings = [1 => 1, 2 => 2, 4 => 3] (response_id => rank)

    // 5. Сохранить голоса (наша логика)
    $this->saveRankedVotes($poll, $rankings);

    // 6. Redirect обратно на тему
    return $this->redirect($this->buildLink('threads', $poll->Thread));
}
```

**Что происходит в БД**:
```sql
-- Сохраняем ранжированные голоса:
DELETE FROM xf_alebarda_ranked_poll_vote
WHERE poll_id = 123 AND user_id = 789;  -- удалить старые если есть

INSERT INTO xf_alebarda_ranked_poll_vote (poll_id, user_id, option_id, rank_position, vote_date) VALUES
  (123, 789, 1, 1, 1735300000),  -- Python = rank 1
  (123, 789, 2, 2, 1735300000),  -- JavaScript = rank 2
  (123, 789, 4, 3, 1735300000);  -- Rust = rank 3

-- Обновляем счётчик проголосовавших в XenForo таблице:
UPDATE xf_poll SET voter_count = voter_count + 1 WHERE poll_id = 123;

-- Также отмечаем в стандартной таблице (для совместимости):
INSERT INTO xf_poll_vote (poll_id, user_id, poll_response_id, vote_date) VALUES
  (123, 789, 0, 1735300000);  -- 0 = ranked vote
```

---

### Шаг 5: Просмотр результатов (наша логика)

#### Кнопка "Показать результаты"

В шаблоне `poll_block_ranked.html`:
```html
<xf:if is="$poll.canViewRankedResults()">
    <a href="{{ link('ranked-polls/results', $poll) }}" class="button">
        📊 Показать результаты
    </a>
<xf:else />
    <div class="message">
        Результаты будут доступны после закрытия опроса
    </div>
</xf:if>
```

#### Проверка прав

```php
// В Entity расширении
public function canViewRankedResults()
{
    $metadata = $this->getRankedMetadata();
    $visitor = \XF::visitor();

    // Админы всегда видят
    if ($visitor->is_admin) {
        return true;
    }

    switch ($metadata['results_visibility']) {
        case 'always':
            return true;

        case 'after_vote':
            return $this->hasVoted($visitor->user_id);

        case 'after_close':
            $now = time();
            return $metadata['close_date'] && $now > $metadata['close_date'];

        case 'never':
            return false;
    }
}
```

#### Страница результатов

**Роут**: `GET /ranked-polls/123/results`

**Контроллер**: `Alebarda\RankedPoll\Pub\Controller\Vote::actionResults()`

**Что показывает**:
```
┌─────────────────────────────────────────────────┐
│ Результаты: Выберите язык программирования      │
├─────────────────────────────────────────────────┤
│ 🏆 Победитель: Python                           │
│                                                 │
│ Schulze Method Results:                         │
│                                                 │
│ 1. Python        (Condorcet winner)             │
│ 2. JavaScript    (2nd place)                    │
│ 3. Rust          (3rd place)                    │
│ 4. Go            (4th place)                    │
│ 5. Java          (5th place)                    │
│                                                 │
│ ─────────────────────────────────────────────── │
│ Pairwise Comparison Matrix:                     │
│                                                 │
│         │ Py │ JS │ Go │ Rust│ Java│             │
│ ─────────┼────┼────┼────┼─────┼─────┤           │
│ Python  │ -  │ 12 │ 15 │  8  │ 18  │             │
│ JS      │ 8  │ -  │ 10 │  6  │ 14  │             │
│ Go      │ 5  │ 10 │ -  │  4  │ 12  │             │
│ Rust    │ 12 │ 14 │ 16 │  -  │ 20  │             │
│ Java    │ 2  │ 6  │ 8  │  0  │ -   │             │
│                                                 │
│ Всего проголосовало: 20 человек                 │
│                                                 │
│ [📋 Список проголосовавших]                     │
└─────────────────────────────────────────────────┘
```

---

### Шаг 6: Список голосовавших

**Роут**: `GET /ranked-polls/123/voters`

**Что показывает**:
```
┌────────────────────────────────────┐
│ Проголосовали (20):                │
├────────────────────────────────────┤
│ 👤 John Doe       27 декабря 10:30 │
│ 👤 Jane Smith     27 декабря 11:15 │
│ 👤 Bob Johnson    27 декабря 12:00 │
│ ...                                │
└────────────────────────────────────┘
```

**НЕ показывает** как именно голосовал каждый пользователь.

---

## Структура файлов

```
Alebarda/RankedPoll/
├── Setup.php                       # Создание таблиц БД
│
├── XF/Entity/Poll.php              # Расширение XF\Entity\Poll
│   ├── isRankedPoll()              # Проверка: ranked или нет
│   ├── getRankedMetadata()         # Получить настройки
│   ├── canViewRankedResults()      # Права на просмотр результатов
│   └── hasVoted($userId)           # Проголосовал ли пользователь
│
├── Pub/Controller/Vote.php         # Контроллер голосования
│   ├── actionIndex()               # Показать форму голосования
│   ├── actionVote()                # Обработать голосование (POST)
│   ├── actionResults()             # Показать результаты
│   └── actionVoters()              # Список голосовавших
│
├── Pub/Controller/Settings.php     # Контроллер настроек
│   ├── actionConfigure()           # Страница настроек
│   └── actionSave()                # Сохранить настройки
│
├── Voting/Schulze.php              # Алгоритм подсчёта
│   └── calculateWinner($votes)     # Метод Шульца
│
├── _output/
│   ├── routes/                     # Роуты
│   │   └── public/
│   │       ├── ranked_poll_vote.json
│   │       ├── ranked_poll_results.json
│   │       └── ranked_poll_settings.json
│   │
│   ├── template_modifications/     # Модификации шаблонов
│   │   └── public/
│   │       └── poll_block_use_ranked.json  # Переключение на ranked интерфейс
│   │
│   └── templates/
│       └── public/
│           ├── poll_block_ranked.html       # Форма голосования
│           ├── poll_results_ranked.html     # Результаты
│           ├── poll_voters_list.html        # Список голосовавших
│           └── ranked_poll_settings.html    # Страница настроек
```

---

## База данных

```sql
-- Стандартные таблицы XenForo (используются как есть):
xf_poll                  # Основная таблица опросов
xf_poll_response         # Варианты ответов
xf_poll_vote             # Факт голосования (для счётчика)

-- Наши таблицы:
xf_alebarda_ranked_poll_metadata
├── poll_id (FK → xf_poll.poll_id)
├── is_ranked (boolean)
├── results_visibility (enum: 'always', 'after_vote', 'after_close', 'never')
├── allowed_user_groups (JSON: [2, 3, 4])
├── open_date (timestamp, nullable)
├── close_date (timestamp, nullable)
└── show_voter_list (boolean)

xf_alebarda_ranked_poll_vote
├── vote_id (PK)
├── poll_id (FK → xf_poll.poll_id)
├── user_id (FK → xf_user.user_id)
├── poll_response_id (FK → xf_poll_response.poll_response_id)
├── rank_position (int: 1, 2, 3, ...)
└── vote_date (timestamp)
```

---

## Что получается в итоге?

### ✅ Преимущества гибридного подхода:

1. **Простое создание опросов**
   - Пользователь создаёт опрос как обычно через XenForo UI
   - Знакомый интерфейс, не нужно учить новое

2. **Гибкие настройки**
   - После создания можно включить ranked voting
   - Настроить права, время, видимость результатов

3. **Минимальный риск поломки**
   - Не расширяем контроллеры XenForo (использовали свои)
   - Стандартные опросы продолжают работать как обычно

4. **Интеграция с форумом**
   - Опросы привязаны к темам
   - Используются разрешения XenForo
   - Работают модераторские функции

5. **Расширяемость**
   - Легко добавить новые фичи
   - Можно менять алгоритм подсчёта
   - Можно добавить экспорт, графики, и т.д.

### ❓ Вопросы?

- Понятна ли теперь идея гибридного подхода?
- Какие моменты требуют дополнительного объяснения?
- Готовы ли начать реализацию этого варианта?
