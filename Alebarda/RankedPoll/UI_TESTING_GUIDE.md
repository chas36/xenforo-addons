# Руководство по тестированию UI создания Ranked Polls

## ✅ Что было загружено на сервер

### 1. **Listener.php** - обработка событий
- `pollEntityPreSave()` - устанавливает `poll_type = 'ranked'` при создании
- `pollEntityPostSave()` - сохраняет metadata в БД

### 2. **Code Event Listeners** (регистрация событий)
- `entity_pre_save_poll.json` - срабатывает перед сохранением опроса
- `entity_post_save_poll.json` - срабатывает после сохранения опроса

### 3. **Template Modification**
- `thread_create_add_ranked_poll_option.json` - добавляет checkbox в форму создания опроса

### 4. **Phrases** (текстовые фразы)
- `alebarda_rankedpoll_enable_ranked_voting` - "Enable ranked-choice voting (Schulze method)"
- `alebarda_rankedpoll_enable_ranked_voting_hint` - "Users will rank options by preference..."

---

## 🧪 Как протестировать

### Шаг 1: Импортировать изменения в XenForo

Подключитесь по SSH и выполните:

```bash
cd /var/www/u0513784/data/www/beta.politsim.ru

# Импортировать code event listeners
php cmd.php xf-dev:import-code-event-listeners

# Импортировать template modifications
php cmd.php xf-dev:import-template-modifications

# Перестроить кэш
php cmd.php xf:rebuild-caches
```

### Шаг 2: Проверить что сайт работает

Откройте в браузере:
```
https://beta.politsim.ru/
```

Сайт должен загружаться нормально.

### Шаг 3: Создать новую тему с опросом

1. Зайдите на форум
2. Нажмите "Create thread" (Создать тему)
3. В форме создания темы прокрутите вниз до раздела "Poll" (Опрос)
4. Заполните вопрос и варианты ответов, например:

```
Poll question: What is your favorite programming language?

Responses:
1. Python
2. JavaScript
3. Go
4. Rust
5. PHP
```

5. **ВАЖНО**: Найдите checkbox **"Enable ranked-choice voting (Schulze method)"**
   - Если checkbox НЕ появился - template modification не применилась
   - Если checkbox есть - поставьте галочку ✓

6. Нажмите "Create thread"

### Шаг 4: Проверить что опрос создан как ranked

После создания темы проверьте в БД:

```bash
cd /var/www/u0513784/data/www/beta.politsim.ru

# Найти ID последнего созданного опроса
php cmd.php xf-db:query "SELECT poll_id, question, poll_type FROM xf_poll ORDER BY poll_id DESC LIMIT 1"
```

**Ожидаемый результат:**
```
poll_id | question                              | poll_type
--------|---------------------------------------|----------
123     | What is your favorite programming ... | ranked
```

Если `poll_type = 'ranked'` - **успех!** ✅

### Шаг 5: Проверить metadata в БД

```bash
php cmd.php xf-db:query "SELECT * FROM xf_alebarda_ranked_poll_metadata WHERE poll_id = ПОСЛЕДНИЙ_POLL_ID"
```

**Ожидаемый результат:**
```
poll_id | is_ranked | results_visibility | allowed_user_groups | ...
--------|-----------|-------------------|---------------------|----
123     | 1         | after_close       | [2]                 | ...
```

### Шаг 6: Проголосовать в опросе

1. Откройте тему с ranked опросом
2. Должны увидеть dropdown'ы для ранжирования (вместо checkbox'ов)
3. Установите ранги:
   ```
   Python     - Rank: 1
   Rust       - Rank: 2
   JavaScript - Rank: 3
   ```
4. Нажмите "Vote"

### Шаг 7: Проверить сохранение голоса

```bash
php cmd.php xf-db:query "SELECT * FROM xf_poll_ranked_vote WHERE poll_id = POLL_ID"
```

**Ожидаемый результат:**
```
vote_id | poll_id | user_id | poll_response_id | rank_position | vote_date
--------|---------|---------|------------------|---------------|----------
1       | 123     | 456     | 1                | 1             | 1735330000
2       | 123     | 456     | 4                | 2             | 1735330000
3       | 123     | 456     | 2                | 3             | 1735330000
```

---

## ❓ Troubleshooting (Что делать если не работает)

### Проблема 1: Checkbox не появляется

**Причина**: Template modification не применилась

**Решение**:
```bash
# Проверить зарегистрирована ли модификация
php cmd.php xf-db:query "SELECT * FROM xf_template_modification WHERE modification_key LIKE '%ranked%'"

# Если пусто - импортировать вручную
php cmd.php xf-dev:import-template-modifications
```

Альтернатива - добавить через Admin панель:
1. Admin CP → Appearance → Template modifications
2. Add template modification
3. Template: `helper_poll_edit`
4. Find: `<xf:checkboxrow label="{{ phrase('poll_options') }}">`
5. Replace: добавить checkbox для ranked voting

### Проблема 2: poll_type не устанавливается в 'ranked'

**Причина**: Event listener не зарегистрирован

**Решение**:
```bash
# Проверить listeners
php cmd.php xf-db:query "SELECT * FROM xf_code_event_listener WHERE callback_class LIKE '%RankedPoll%'"

# Должно быть 3 записи (templater_template_pre_render, entity_pre_save, entity_post_save)
```

### Проблема 3: Metadata не сохраняется

**Причина**: Таблица `xf_alebarda_ranked_poll_metadata` не существует

**Решение**:
```bash
# Проверить существование таблицы
php cmd.php xf-db:query "SHOW TABLES LIKE 'xf_alebarda_ranked_poll_metadata'"

# Если таблицы нет - запустить Setup.php
php cmd.php xf-addon:install Alebarda/RankedPoll
```

### Проблема 4: Белая страница после изменений

**Причина**: PHP ошибка в коде

**Решение**:
```bash
# Проверить логи ошибок
tail -50 /var/www/u0513784/data/logs/error_log

# Проверить синтаксис Listener.php
php -l /var/www/u0513784/data/www/beta.politsim.ru/src/addons/Alebarda/RankedPoll/Listener.php
```

### Проблема 5: Голосование не сохраняется

**Причина**: Repository расширение не работает

**Решение**:
```bash
# Проверить что Repository расширение загружено
ls -la /var/www/u0513784/data/www/beta.politsim.ru/src/addons/Alebarda/RankedPoll/XF/Repository/PollRepository.php

# Очистить кэш
php cmd.php xf:rebuild-caches
```

---

## 📋 Checklist для тестирования

- [ ] Сайт загружается нормально
- [ ] При создании темы видно раздел "Poll"
- [ ] В разделе Poll есть checkbox "Enable ranked-choice voting"
- [ ] После включения checkbox и создания темы, `poll_type = 'ranked'` в БД
- [ ] Metadata сохраняется в `xf_alebarda_ranked_poll_metadata`
- [ ] При просмотре опроса видны dropdown'ы для ранжирования
- [ ] После голосования данные сохраняются в `xf_poll_ranked_vote`
- [ ] Можно проголосовать повторно (если `change_vote = true`)

---

## 🎯 Что дальше после успешного теста

Если всё работает, следующие шаги:

### 1. Создать контроллер для просмотра результатов
Файл: `Pub/Controller/RankedPoll.php`
- `actionResults()` - показать результаты Schulze
- `actionVoters()` - список голосовавших

### 2. Создать шаблон результатов
Файл: `_output/templates/public/poll_results_ranked.html`
- Победитель
- Полное ранжирование
- Pairwise comparison matrix
- Статистика

### 3. Зарегистрировать роуты
- `/ranked-polls/{poll_id}/results` → результаты
- `/ranked-polls/{poll_id}/voters` → список голосовавших

### 4. Добавить кнопку "View Results" в poll_block_ranked.html

---

## 💡 Полезные команды

```bash
# Проверить версию аддона
php cmd.php xf-addon:list | grep RankedPoll

# Экспортировать изменения
php cmd.php xf-addon:export Alebarda/RankedPoll

# Перестроить master data
php cmd.php xf-dev:rebuild-master-data

# Проверить ошибки PHP
php -l src/addons/Alebarda/RankedPoll/**/*.php
```

---

Удачи с тестированием! 🚀
