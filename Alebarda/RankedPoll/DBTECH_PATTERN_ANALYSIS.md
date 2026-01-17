# Анализ паттернов DBTech Credits для расширения Poll

## Обнаруженные файлы

```
DBTech/Credits/
├── XF/Entity/Poll.php              # Расширение Entity
├── XF/Repository/PollRepository.php # Расширение Repository
├── EventTrigger/PollHandler.php    # Обработчик событий
└── Listener.php                    # Регистрация событий
```

---

## Ключевые паттерны безопасного расширения

### 1. XFCP Pattern (XenForo Class Proxy)

**Что такое XFCP?**
```php
class Poll extends XFCP_Poll
{
    // ...
}
```

- `XFCP_Poll` - это **динамически генерируемый класс**
- XenForo автоматически создаёт цепочку наследования
- Несколько аддонов могут расширять один класс без конфликтов

**Как это работает:**
```
XF\Entity\Poll (оригинальный класс)
    ↓
XFCP_Poll (автоматически генерируется XenForo)
    ↓
DBTech\Credits\XF\Entity\Poll (наше расширение)
```

Если есть другой аддон:
```
XF\Entity\Poll
    ↓
XFCP_Poll_1 (автоматически)
    ↓
Addon1\XF\Entity\Poll
    ↓
XFCP_Poll_2 (автоматически)
    ↓
DBTech\Credits\XF\Entity\Poll
```

### 2. Безопасные точки расширения

DBTech Credits использует **protected методы lifecycle hooks**:

```php
class Poll extends XFCP_Poll
{
    protected function _preSave()
    {
        parent::_preSave();  // ← КРИТИЧНО! Всегда вызываем parent

        // Наша логика ПОСЛЕ parent
        // ...
    }

    protected function _postSave()
    {
        parent::_postSave();  // ← КРИТИЧНО!

        // Наша логика
    }

    protected function _preDelete()
    {
        parent::_preDelete();
    }

    protected function _postDelete()
    {
        parent::_postDelete();
    }
}
```

**Почему это безопасно:**
- ✅ Не переопределяет публичные методы
- ✅ Всегда вызывает `parent::` (не ломает другие расширения)
- ✅ Использует хуки, предназначенные для расширения

### 3. Repository расширение

```php
namespace DBTech\Credits\XF\Repository;

class PollRepository extends XFCP_PollRepository
{
    public function voteOnPoll(Poll $poll, $votes, ?User $voter = null)
    {
        // 1. Добавляем свою логику ДО родительского метода
        $previousVotes = $this->db()->fetchAllKeyed('...');

        // ... выполняем свои действия (event triggers)

        // 2. Вызываем оригинальный метод
        return parent::voteOnPoll($poll, $votes, $voter);
    }
}
```

**Ключевой момент:**
- Расширяет ПУБЛИЧНЫЙ метод `voteOnPoll`
- НО всегда вызывает `parent::voteOnPoll()` в конце
- Добавляет логику ДО и/или ПОСЛЕ оригинального метода

---

## Почему контроллеры ломались у нас?

### Проблема с контроллерами

Наши попытки расширить `XF\Pub\Controller\Poll` ломали форум, потому что:

1. **Контроллеры - точка входа**
   - Когда контроллер ломается, вся страница падает
   - Нет fallback механизма

2. **Сложные зависимости**
   - Контроллеры используют много других классов
   - Одна ошибка в цепочке = белая страница

3. **Routing проблемы**
   - Если route не может найти контроллер = 500 error

### Почему Entity/Repository безопаснее?

1. **Изолированная логика**
   - Entity/Repository - это data layer
   - Ошибка не ломает весь request

2. **Хуки предназначены для расширения**
   - `_preSave()`, `_postSave()` - это официальные extension points
   - XenForo тестирует их на совместимость

3. **Множественное наследование работает**
   - XFCP корректно обрабатывает цепочки Entity/Repository
   - С контроллерами это менее надёжно

---

## Применение паттернов к RankedPoll

### Что можем безопасно расширить:

#### 1. Entity Poll (✅ УЖЕ СДЕЛАЛИ)

```php
namespace Alebarda\RankedPoll\XF\Entity;

class Poll extends XFCP_Poll
{
    // ✅ Добавление методов
    public function isRankedPoll()
    {
        // ...
    }

    public function getRankedMetadata()
    {
        // ...
    }

    public function canViewRankedResults()
    {
        // ...
    }

    // ✅ Расширение lifecycle hooks
    protected function _postSave()
    {
        parent::_postSave();

        // Инвалидировать кэш ranked результатов
        if ($this->isRankedPoll() && $this->isChanged('close_date'))
        {
            $this->invalidateRankedCache();
        }
    }
}
```

#### 2. Repository PollRepository (🆕 НУЖНО ДОБАВИТЬ)

```php
namespace Alebarda\RankedPoll\XF\Repository;

use XF\Entity\Poll;
use XF\Entity\User;

class PollRepository extends XFCP_PollRepository
{
    /**
     * Расширяем метод голосования для ranked polls
     */
    public function voteOnPoll(Poll $poll, $votes, ?User $voter = null)
    {
        // Проверяем: ranked poll?
        if ($poll->isRankedPoll())
        {
            // НЕ вызываем parent! Полностью заменяем логику для ranked
            return $this->voteOnRankedPoll($poll, $votes, $voter);
        }

        // Для обычных polls - вызываем оригинальный метод
        return parent::voteOnPoll($poll, $votes, $voter);
    }

    /**
     * Наша логика голосования для ranked polls
     */
    protected function voteOnRankedPoll(Poll $poll, array $rankings, ?User $voter = null)
    {
        $voter = $voter ?: \XF::visitor();

        // Валидация
        if (!$poll->canVote($error))
        {
            throw new \XF\PrintableException($error);
        }

        // Сохранение ranked votes
        $db = $this->db();
        $db->beginTransaction();

        // Удалить старые голоса
        $db->delete('xf_alebarda_ranked_poll_vote',
            'poll_id = ? AND user_id = ?',
            [$poll->poll_id, $voter->user_id]
        );

        // Вставить новые
        foreach ($rankings as $responseId => $rank)
        {
            if ($rank > 0) // только проранжированные
            {
                $db->insert('xf_alebarda_ranked_poll_vote', [
                    'poll_id' => $poll->poll_id,
                    'user_id' => $voter->user_id,
                    'poll_response_id' => $responseId,
                    'rank_position' => $rank,
                    'vote_date' => \XF::$time
                ]);
            }
        }

        // Обновить счётчик
        $hasVotedBefore = $db->fetchOne("
            SELECT 1 FROM xf_poll_vote
            WHERE poll_id = ? AND user_id = ?
        ", [$poll->poll_id, $voter->user_id]);

        if (!$hasVotedBefore)
        {
            $db->insert('xf_poll_vote', [
                'poll_id' => $poll->poll_id,
                'user_id' => $voter->user_id,
                'poll_response_id' => 0, // ranked marker
                'vote_date' => \XF::$time
            ]);

            $poll->voter_count++;
            $poll->save();
        }

        $db->commit();

        return true;
    }
}
```

#### 3. Service Poll\Creator (🆕 БЕЗОПАСНОЕ РАСШИРЕНИЕ)

```php
namespace Alebarda\RankedPoll\XF\Service\Poll;

class Creator extends XFCP_Creator
{
    protected $enableRankedVoting = false;
    protected $rankedSettings = [];

    /**
     * Добавляем метод для включения ranked voting
     */
    public function enableRankedVoting(array $settings = [])
    {
        $this->enableRankedVoting = true;
        $this->rankedSettings = array_merge([
            'results_visibility' => 'after_close',
            'allowed_user_groups' => [],
            'open_date' => null,
            'close_date' => null,
            'show_voter_list' => true
        ], $settings);
    }

    /**
     * Расширяем метод сохранения
     */
    protected function _save()
    {
        // Вызываем оригинальный метод
        $poll = parent::_save();

        // ПОСЛЕ успешного создания опроса
        if ($this->enableRankedVoting && $poll)
        {
            $this->saveRankedMetadata($poll);
        }

        return $poll;
    }

    protected function saveRankedMetadata($poll)
    {
        $this->db()->insert('xf_alebarda_ranked_poll_metadata', [
            'poll_id' => $poll->poll_id,
            'is_ranked' => 1,
            'results_visibility' => $this->rankedSettings['results_visibility'],
            'allowed_user_groups' => json_encode($this->rankedSettings['allowed_user_groups']),
            'open_date' => $this->rankedSettings['open_date'],
            'close_date' => $this->rankedSettings['close_date'],
            'show_voter_list' => $this->rankedSettings['show_voter_list']
        ]);
    }
}
```

---

## Что НЕ расширяем (используем свои контроллеры)

### ❌ Не расширяем XF\Pub\Controller\Poll

**Вместо этого:**
```php
// Свой контроллер для ranked функциональности
namespace Alebarda\RankedPoll\Pub\Controller;

class RankedPoll extends \XF\Pub\Controller\AbstractController
{
    public function actionVote(ParameterBag $params)
    {
        // Полностью наша логика
        $poll = $this->assertViewablePoll($params->poll_id);

        if (!$poll->isRankedPoll())
        {
            return $this->error('Not a ranked poll');
        }

        // Используем Repository для голосования
        /** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
        $pollRepo = $this->repository('XF:PollRepository');

        $rankings = $this->filter('rankings', 'array-uint');
        $pollRepo->voteOnPoll($poll, $rankings);

        return $this->redirect($this->buildLink('threads', $poll->Thread));
    }
}
```

**Почему так безопаснее:**
- ✅ Наш контроллер только для ranked polls
- ✅ Не ломает стандартные опросы
- ✅ Легко отладить
- ✅ Можно удалить без последствий

---

## Регистрация расширений

### Автоматическая регистрация по структуре папок

XenForo **автоматически** регистрирует расширения если:

1. **Правильная структура папок:**
```
Alebarda/RankedPoll/
└── XF/
    ├── Entity/
    │   └── Poll.php          # Расширяет XF\Entity\Poll
    ├── Repository/
    │   └── PollRepository.php # Расширяет XF\Repository\PollRepository
    └── Service/
        └── Poll/
            └── Creator.php    # Расширяет XF\Service\Poll\Creator
```

2. **Правильный namespace:**
```php
namespace Alebarda\RankedPoll\XF\Entity;  // ← Путь должен совпадать!
```

3. **Правильное наследование:**
```php
class Poll extends XFCP_Poll  // ← Всегда XFCP_{ClassName}
```

### Проверка регистрации

После загрузки на сервер:
```bash
php cmd.php xf-dev:class-extensions
```

Должно показать:
```
XF\Entity\Poll
  ↳ Alebarda\RankedPoll\XF\Entity\Poll

XF\Repository\PollRepository
  ↳ Alebarda\RankedPoll\XF\Repository\PollRepository

XF\Service\Poll\Creator
  ↳ Alebarda\RankedPoll\XF\Service\Poll\Creator
```

---

## Checklist безопасного расширения

### ✅ Перед написанием кода:

- [ ] Используем XFCP pattern
- [ ] Наследуемся от `XFCP_{ClassName}`
- [ ] ВСЕГДА вызываем `parent::` методы
- [ ] Не переопределяем критичные публичные методы полностью (только добавляем логику)
- [ ] Используем lifecycle hooks (`_preSave`, `_postSave`, etc.)

### ✅ При расширении Repository:

- [ ] Если меняем поведение метода - проверяем условие (например `if ($poll->isRankedPoll())`)
- [ ] Для стандартного behaviour всегда вызываем `parent::method()`
- [ ] Используем transactions для DB операций

### ✅ При расширении Entity:

- [ ] Добавляем только новые методы ИЛИ расширяем protected hooks
- [ ] Не меняем публичный API без крайней необходимости
- [ ] Кэшируем тяжёлые вычисления

### ✅ При создании своих контроллеров:

- [ ] Используем отдельный namespace (не `XF\Pub\Controller`)
- [ ] Регистрируем свои routes
- [ ] Переиспользуем существующие Repository/Entity (не дублируем логику)

---

## Итоговая архитектура для RankedPoll

```
Расширения XenForo (безопасно):
├── XF/Entity/Poll.php              ✅ Добавляем методы + lifecycle hooks
├── XF/Repository/PollRepository.php ✅ Расширяем voteOnPoll()
└── XF/Service/Poll/Creator.php     ✅ Добавляем ranked metadata при создании

Наши контроллеры (изолированно):
├── Pub/Controller/RankedPoll.php   ✅ Голосование, результаты
└── Pub/Controller/Settings.php     ✅ Настройки ranked poll

Наши сервисы:
├── Voting/Schulze.php              ✅ Алгоритм подсчёта
└── Service/RankedPoll/Converter.php ✅ Конвертация стандартного poll → ranked

Routes:
├── ranked-polls/vote               ✅ POST голосование
├── ranked-polls/results            ✅ GET результаты
├── ranked-polls/voters             ✅ GET список голосовавших
└── ranked-polls/configure          ✅ GET/POST настройки
```

---

## Следующие шаги

1. **Создать Repository расширение** (по паттерну DBTech)
2. **Создать Service\Poll\Creator расширение** (для создания ranked polls через UI)
3. **Протестировать расширения** (загрузить на сервер, проверить что форум работает)
4. **Добавить свои контроллеры** (для функциональности, которую нельзя расширить)

Готовы начать реализацию?
