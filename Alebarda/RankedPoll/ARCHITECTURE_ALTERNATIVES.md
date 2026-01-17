# Альтернативные архитектуры для RankedPoll

## Ваши требования

1. **Контроль доступа**: Ограничение доступа к голосованию для разных групп пользователей
2. **Управление опросами**: Создание, редактирование, закрытие опросов
3. **Временные рамки**: Настройка времени открытия и закрытия опросов
4. **Видимость результатов**: Реальное время ИЛИ только после завершения
5. **Список голосовавших**: Показывать кто проголосовал (но не их выбор)
6. **Безопасность вставки**: HTML/CSS/JS код для вставки опросов должен быть защищён от модификации

---

## Альтернатива 1: Расширение встроенных опросов XenForo

### Описание
Использовать существующую систему `xf_poll` как основу, добавляя ranked-voting через дополнительные таблицы и расширения Entity/Service.

### Архитектура
```
xf_poll (существующая)
├── poll_type: 'standard' | 'ranked'
├── close_date: timestamp
└── (используем встроенные permissions)

xf_poll_ranked_vote (новая)
├── poll_id
├── user_id
├── poll_response_id
├── rank_position
└── vote_date

xf_poll_ranked_settings (новая)
├── poll_id
├── results_visibility: 'realtime' | 'after_close' | 'never'
├── allowed_user_groups: JSON array [2, 3, 5]
├── open_date: timestamp (nullable)
└── close_date: timestamp (nullable)
```

### Как реализовать требования

| Требование | Реализация |
|------------|------------|
| Контроль доступа | `Poll::canVote()` проверяет `allowed_user_groups` из `xf_poll_ranked_settings` |
| Управление опросами | Расширить `XF\Service\Poll\Creator` и `XF\Service\Poll\Editor` |
| Временные рамки | Проверка `open_date` и `close_date` в `Poll::canVote()` |
| Видимость результатов | Метод `Poll::canViewRankedResults()` проверяет `results_visibility` и статус опроса |
| Список голосовавших | SQL запрос: `SELECT DISTINCT user_id FROM xf_poll_ranked_vote WHERE poll_id = ?` |
| Безопасность вставки | BB code `[rankedpoll=ID]` генерирует read-only HTML (нет inline JS) |

### Плюсы
- ✅ Интегрируется с существующей системой XenForo
- ✅ Использует встроенные разрешения и группы пользователей
- ✅ Опросы привязаны к темам (как обычные polls)
- ✅ Модераторы могут управлять через админ-панель

### Минусы
- ❌ Риск поломки форума при расширении контроллеров (уже было)
- ❌ Сложно добавить UI для создания ranked polls
- ❌ Ограничены архитектурой XenForo polls

### Защита от модификации HTML/CSS/JS
**BB Code подход**:
```html
[rankedpoll=123]
```

Генерирует:
```html
<div class="rankedPoll" data-poll-id="123" data-xf-init="ranked-poll">
    <!-- HTML рендерится на сервере -->
    <!-- JS только инициализирует интерактивность через data-attributes -->
    <!-- Пользователь не может изменить параметры опроса -->
</div>
```

**Безопасность**: Все параметры опроса загружаются из БД при рендере, не из BB code.

---

## Альтернатива 2: Полностью независимая система опросов

### Описание
Создать отдельную систему опросов с собственными таблицами, контроллерами и UI.

### Архитектура
```
xf_alebarda_ranked_poll
├── poll_id (PK, auto-increment)
├── title
├── description
├── created_by_user_id
├── created_date
├── open_date (nullable)
├── close_date (nullable)
├── results_visibility: 'realtime' | 'after_close' | 'after_vote' | 'never'
├── allowed_user_groups: JSON array
├── show_voter_list: boolean
└── status: 'draft' | 'open' | 'closed'

xf_alebarda_ranked_poll_option
├── option_id (PK)
├── poll_id (FK)
├── option_text
└── display_order

xf_alebarda_ranked_poll_vote
├── vote_id (PK)
├── poll_id (FK)
├── user_id (FK)
├── option_id (FK)
├── rank_position
└── vote_date

xf_alebarda_ranked_poll_permission
├── poll_id (FK)
├── user_group_id (FK)
└── permission_type: 'view' | 'vote' | 'edit' | 'view_results'
```

### Контроллеры
```
Alebarda\RankedPoll\Pub\Controller\Poll
├── actionIndex()        - Список всех опросов
├── actionView()         - Просмотр опроса
├── actionVote()         - Голосование
├── actionResults()      - Результаты
├── actionVoterList()    - Список проголосовавших
├── actionCreate()       - Создание (для авторизованных)
├── actionEdit()         - Редактирование (автор или модератор)
└── actionClose()        - Закрытие опроса

Alebarda\RankedPoll\Admin\Controller\RankedPoll
└── (управление через админ-панель)
```

### Routes
```
/ranked-polls/                          → список
/ranked-polls/123/                      → просмотр
/ranked-polls/123/vote                  → голосование (POST)
/ranked-polls/123/results               → результаты
/ranked-polls/123/voters                → список проголосовавших
/ranked-polls/create                    → создание
/ranked-polls/123/edit                  → редактирование
/ranked-polls/123/close                 → закрытие
```

### Как реализовать требования

| Требование | Реализация |
|------------|------------|
| Контроль доступа | Проверка `xf_alebarda_ranked_poll_permission` в `Poll::canVote()` |
| Управление опросами | Отдельные формы: `/ranked-polls/create`, `/ranked-polls/{id}/edit` |
| Временные рамки | Колонки `open_date`, `close_date` с проверкой в контроллере |
| Видимость результатов | Проверка `results_visibility` + статус опроса + права пользователя |
| Список голосовавших | SQL с JOIN на `xf_user` если `show_voter_list = 1` |
| Безопасность вставки | BB code или embed URL с signed параметрами |

### Плюсы
- ✅ Полный контроль над функциональностью
- ✅ Независимая от XenForo polls
- ✅ Легко расширять и добавлять фичи
- ✅ Не рискуем сломать существующие опросы

### Минусы
- ❌ Нужно дублировать логику разрешений XenForo
- ❌ Больше кода для написания
- ❌ Опросы не привязаны к темам форума (отдельная сущность)

### Защита от модификации HTML/CSS/JS

**Вариант A: BB Code с signed ID**
```html
[rankedpoll=123]
```

Сервер генерирует HTML с data-атрибутами, JS инициализируется через XenForo framework.

**Вариант B: Iframe embed**
```html
[rankedpoll=123]
```

Генерирует:
```html
<iframe src="/ranked-polls/123/embed?signature=HMAC"
        width="100%"
        height="500"
        frameborder="0"
        sandbox="allow-scripts allow-same-origin">
</iframe>
```

- `signature` - HMAC подпись на основе poll_id + secret key
- Iframe изолирует опрос от основной страницы
- Пользователь не может изменить poll_id без нарушения подписи

**Вариант C: Web Component**
```html
<alebarda-ranked-poll poll-id="123" poll-signature="HMAC"></alebarda-ranked-poll>
<script src="/js/alebarda/ranked-poll-widget.js"></script>
```

- Web Component регистрируется XenForo
- Проверяет подпись перед загрузкой данных
- Полностью инкапсулированный UI

---

## Альтернатива 3: Гибридный подход (Рекомендуется)

### Описание
Использовать XenForo polls для создания (через темы), но собственную систему для ranked voting + расширенные настройки.

### Архитектура
```
xf_poll (существующая, только для создания)
└── Создаётся через thread creation как обычно

xf_alebarda_ranked_poll_metadata
├── poll_id (FK → xf_poll)
├── poll_type: 'ranked'
├── open_date
├── close_date
├── results_visibility
├── allowed_user_groups: JSON
└── show_voter_list: boolean

xf_alebarda_ranked_poll_vote
├── poll_id (FK)
├── user_id
├── poll_response_id (FK → xf_poll_response)
├── rank_position
└── vote_date
```

### Workflow

1. **Создание**: Пользователь создаёт тему с опросом (стандартный XF flow)
2. **Конвертация**: Через UI или автоматически, опрос помечается как `ranked`
3. **Настройка**: Автор/модератор добавляет расширенные настройки через `/ranked-polls/{id}/settings`
4. **Голосование**: Через отдельный контроллер `/ranked-polls/{id}/vote`
5. **Результаты**: Через `/ranked-polls/{id}/results` с проверкой прав

### Как реализовать требования

| Требование | Реализация |
|------------|------------|
| Контроль доступа | Проверка `allowed_user_groups` из metadata таблицы |
| Управление опросами | Редактирование через XF + расширенные настройки через `/settings` страницу |
| Временные рамки | Metadata таблица, проверка в `Poll::canVoteRanked()` |
| Видимость результатов | Metadata таблица + логика в `Poll::canViewRankedResults()` |
| Список голосовавших | Отдельная страница `/ranked-polls/{id}/voters` с проверкой прав |
| Безопасность вставки | Poll привязан к thread, BB code `[thread=123]` автоматически показывает ranked poll |

### Плюсы
- ✅ Использует существующий UI для создания опросов
- ✅ Не ломает стандартные опросы
- ✅ Расширенная функциональность через отдельные контроллеры
- ✅ Опросы привязаны к темам

### Минусы
- ❌ Более сложная архитектура
- ❌ Требуется UI для конвертации стандартных polls в ranked

### Защита от модификации
- Опрос привязан к thread_id
- XenForo автоматически вставляет опрос в тему
- BB code не нужен, опрос показывается автоматически в теме
- Все параметры загружаются из БД по thread_id

---

## Альтернатива 4: API + Standalone Widget

### Описание
Backend API на XenForo, frontend как отдельное приложение (React/Vue) которое встраивается через iframe или Web Component.

### Архитектура

**Backend (XenForo)**:
```
API Routes:
POST   /api/ranked-polls/           - создать опрос
GET    /api/ranked-polls/{id}       - получить опрос
PUT    /api/ranked-polls/{id}       - обновить опрос
DELETE /api/ranked-polls/{id}       - удалить опрос
POST   /api/ranked-polls/{id}/vote  - проголосовать
GET    /api/ranked-polls/{id}/results - результаты
GET    /api/ranked-polls/{id}/voters  - список голосовавших

Аутентификация: XenForo API keys или session cookies
Permissions: Проверка через XenForo permission system
```

**Frontend (Standalone)**:
```javascript
// React/Vue компонент
<RankedPoll
    pollId={123}
    apiUrl="https://forum.example.com/api"
    authToken="XF_SESSION_TOKEN"
/>
```

**Embed**:
```html
<!-- Сервер генерирует embed code -->
<div id="ranked-poll-123"></div>
<script>
window.RankedPollConfig = {
    pollId: 123,
    apiUrl: 'https://forum.example.com/api',
    authToken: '<?= $xf->session->get('session_token') ?>',
    readOnly: false
};
</script>
<script src="https://cdn.example.com/ranked-poll-widget.js"></script>
```

### Как реализовать требования

| Требование | Реализация |
|------------|------------|
| Контроль доступа | API проверяет права через XenForo permissions |
| Управление опросами | Frontend UI + API endpoints |
| Временные рамки | API возвращает `open_date`, `close_date`, frontend показывает countdown |
| Видимость результатов | API endpoint `/results` проверяет права перед отдачей данных |
| Список голосовавших | API endpoint `/voters` с пагинацией |
| Безопасность вставки | Widget загружается с CDN, конфигурация подписывается HMAC |

### Плюсы
- ✅ Максимальная гибкость frontend
- ✅ Можно использовать современные фреймворки (React, Vue)
- ✅ Полная изоляция через iframe (безопасность)
- ✅ Можно встраивать на любые сайты, не только XenForo

### Минусы
- ❌ Требует знания JavaScript фреймворков
- ❌ Более сложная разработка (backend + frontend)
- ❌ CDN для хостинга widget
- ❌ CORS настройки

### Защита от модификации

**Signed Embed Code**:
```html
[rankedpoll id="123" signature="HMAC_SHA256(123 + SECRET)"]
```

BB code парсер генерирует:
```html
<div class="ranked-poll-container"
     data-poll-id="123"
     data-signature="a3f5b9c..."
     data-xf-init="ranked-poll-widget">
</div>
```

**Widget инициализация**:
```javascript
// Widget проверяет подпись перед загрузкой
XF.RankedPollWidget = XF.Element.newHandler({
    init: function() {
        const pollId = this.$target.data('poll-id');
        const signature = this.$target.data('signature');

        // Отправить на сервер для проверки
        this.verifyAndLoad(pollId, signature);
    },

    verifyAndLoad: function(pollId, signature) {
        $.post('/api/ranked-polls/verify', {
            poll_id: pollId,
            signature: signature
        }).done((data) => {
            if (data.valid) {
                this.loadPoll(pollId);
            }
        });
    }
});
```

---

## Сравнительная таблица

| Критерий | Alt 1: Расширение XF | Alt 2: Независимая | Alt 3: Гибрид | Alt 4: API + Widget |
|----------|---------------------|-------------------|--------------|---------------------|
| **Сложность разработки** | Средняя | Высокая | Высокая | Очень высокая |
| **Риск поломки форума** | Высокий | Низкий | Средний | Низкий |
| **Интеграция с XF** | Отличная | Слабая | Хорошая | Средняя |
| **Гибкость функций** | Ограниченная | Полная | Полная | Максимальная |
| **Безопасность embed** | Хорошая | Отличная | Хорошая | Отличная |
| **Контроль доступа** | XF permissions | Кастомная | XF + кастомная | XF API |
| **UI/UX гибкость** | Ограниченная | Средняя | Средняя | Максимальная |
| **Время разработки** | 1-2 недели | 3-4 недели | 2-3 недели | 4-6 недель |

---

## Рекомендация

### Для быстрого MVP: **Альтернатива 3 (Гибрид)**

**Почему**:
- Использует существующий UI XenForo для создания опросов
- Минимальный риск поломки (отдельные контроллеры)
- Покрывает все ваши требования
- Можно запустить в production быстро

**Roadmap**:
1. **Неделя 1**: Таблицы БД + Entity расширения
2. **Неделя 2**: Контроллеры голосования + результатов
3. **Неделя 3**: UI для настроек ranked опросов
4. **Неделя 4**: Schulze алгоритм + визуализация результатов
5. **Неделя 5**: Тестирование + багфиксы

### Для долгосрочного проекта: **Альтернатива 4 (API + Widget)**

**Почему**:
- Максимальная гибкость
- Лучшая безопасность через изоляцию
- Можно переиспользовать на других проектах
- Современный stack (React/Vue)

**Roadmap**:
1. **Недели 1-2**: Backend API (Laravel/XenForo)
2. **Недели 3-4**: Frontend Widget (React)
3. **Неделя 5**: Интеграция + BB code
4. **Неделя 6**: Schulze + результаты
5. **Недели 7-8**: Тестирование + оптимизация

---

## Вопросы для принятия решения

1. **Приоритет**: Скорость разработки или долгосрочная гибкость?
2. **Навыки команды**: Есть ли frontend разработчики (React/Vue)?
3. **Бюджет хостинга**: Готовы ли использовать CDN для widget?
4. **Интеграция**: Нужна ли привязка к темам форума или опросы независимые?
5. **Переиспользование**: Планируется ли использовать на других сайтах?

Жду ваш выбор или дополнительные вопросы!
