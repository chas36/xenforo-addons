# Просмотр результатов Ranked Poll - Сводка

## ✅ Что создано

### 1. Контроллер для просмотра результатов
**Файл**: `Pub/Controller/RankedPoll.php`

**Действия**:
- `actionResults()` - отображение результатов Schulze
  - Получает все голоса из БД
  - Запускает алгоритм Schulze
  - Показывает победителя, ранжирование, pairwise matrix

- `actionVoters()` - список голосовавших
  - Получает список пользователей, которые проголосовали
  - Показывает username и дату голосования

### 2. Шаблоны

**poll_results_ranked.html**:
- Показывает победителя с иконкой 🏆
- Полное ранжирование кандидатов (#1, #2, #3...)
- Pairwise comparison matrix (таблица head-to-head сравнений)
- Статистика (общее количество голосов)
- Кнопки: "View Voters", "Back to Thread"

**poll_voters_ranked.html**:
- Список пользователей с аватарами
- Дата/время голосования
- Кнопки: "View Results", "Back to Thread"

**poll_block_ranked.html** (обновлён):
- Добавлена кнопка "View Results" с иконкой
- Показывается только если `canViewRankedResults()` = true

### 3. Роуты

**ranked_poll_results.json**:
- URL: `/ranked-polls/results/{poll_id}`
- Контроллер: `Alebarda\RankedPoll:RankedPoll`
- Действие: `Results`

**ranked_poll_voters.json**:
- URL: `/ranked-polls/voters/{poll_id}`
- Контроллер: `Alebarda\RankedPoll:RankedPoll`
- Действие: `Voters`

### 4. Phrases (фразы для локализации)

- `final_ranking` - "Final Ranking"
- `pairwise_comparison_matrix` - "Pairwise Comparison Matrix"
- `alebarda_rankedpoll_pairwise_explanation` - Объяснение матрицы
- `users_who_voted` - "Users who voted"
- `back_to_thread` - "Back to thread"

---

## 📦 Файлы для загрузки на сервер

```
Alebarda/RankedPoll/
├── Pub/Controller/RankedPoll.php                                    (НОВЫЙ)
├── _output/
│   ├── templates/public/
│   │   ├── poll_results_ranked.html                                 (НОВЫЙ)
│   │   ├── poll_voters_ranked.html                                  (НОВЫЙ)
│   │   └── poll_block_ranked.html                                   (ОБНОВЛЁН)
│   ├── routes/public/
│   │   ├── ranked_poll_results.json                                 (НОВЫЙ)
│   │   └── ranked_poll_voters.json                                  (НОВЫЙ)
│   └── phrases/
│       ├── final_ranking.txt                                        (НОВЫЙ)
│       ├── pairwise_comparison_matrix.txt                           (НОВЫЙ)
│       ├── alebarda_rankedpoll_pairwise_explanation.txt             (НОВЫЙ)
│       ├── users_who_voted.txt                                      (НОВЫЙ)
│       └── back_to_thread.txt                                       (НОВЫЙ)
```

---

## 🚀 Инструкция по загрузке

### Вариант 1: Через SFTP/FTP

1. Используйте FileZilla или другой FTP клиент
2. Подключитесь к серверу (server212.hosting.reg.ru)
3. Перейдите в: `/var/www/u0513784/data/www/beta.politsim.ru/src/addons/Alebarda/RankedPoll/`
4. Загрузите файлы согласно структуре выше

### Вариант 2: Через панель управления хостинга

1. Зайдите в панель управления хостинга
2. Откройте файловый менеджер
3. Перейдите в директорию аддона
4. Загрузите файлы

### Вариант 3: Создать архив локально

```bash
# На вашем компьютере
cd /path/to/xenforo-addons/Alebarda/RankedPoll
zip -r rankedpoll_results.zip \
  Pub/Controller/RankedPoll.php \
  _output/templates/public/poll_results_ranked.html \
  _output/templates/public/poll_voters_ranked.html \
  _output/templates/public/poll_block_ranked.html \
  _output/routes/public/ranked_poll_results.json \
  _output/routes/public/ranked_poll_voters.json \
  _output/phrases/final_ranking.txt \
  _output/phrases/pairwise_comparison_matrix.txt \
  _output/phrases/alebarda_rankedpoll_pairwise_explanation.txt \
  _output/phrases/users_who_voted.txt \
  _output/phrases/back_to_thread.txt
```

Затем загрузите `rankedpoll_results.zip` через панель управления и распакуйте.

---

## ⚙️ После загрузки файлов

### 1. Импортировать изменения

Подключитесь по SSH:

```bash
cd /var/www/u0513784/data/www/beta.politsim.ru

# Импортировать роуты
php cmd.php xf-dev:import-routes

# Импортировать шаблоны
php cmd.php xf-dev:import-templates

# Импортировать phrases
php cmd.php xf-dev:import-phrases

# Перестроить кэш
php cmd.php xf:rebuild-caches
```

### 2. Проверить что файлы загружены

```bash
# Проверить контроллер
ls -la /var/www/u0513784/data/www/beta.politsim.ru/src/addons/Alebarda/RankedPoll/Pub/Controller/RankedPoll.php

# Проверить шаблоны
ls -la /var/www/u0513784/data/www/beta.politsim.ru/src/addons/Alebarda/RankedPoll/_output/templates/public/poll_results_ranked.html

# Проверить роуты
ls -la /var/www/u0513784/data/www/beta.politsim.ru/src/addons/Alebarda/RankedPoll/_output/routes/public/ranked_poll_*.json
```

### 3. Проверить синтаксис

```bash
cd /var/www/u0513784/data/www/beta.politsim.ru

# Проверить PHP синтаксис
php -l src/addons/Alebarda/RankedPoll/Pub/Controller/RankedPoll.php
```

Должно вывести: `No syntax errors detected`

---

## 🧪 Тестирование

### Шаг 1: Создать тестовый ranked poll

1. Зайти на форум
2. Create thread → заполнить опрос
3. ✓ Enable ranked-choice voting
4. Create thread

### Шаг 2: Проголосовать

1. Открыть созданную тему
2. Увидите dropdown'ы для ранжирования
3. Установите ранги (например: Python=1, Rust=2, JS=3)
4. Нажмите "Cast Vote"

### Шаг 3: Просмотреть результаты

1. После голосования должна появиться кнопка **"View Results"** (если разрешено)
2. Нажмите на кнопку
3. Должны увидеть:
   - 🏆 Победитель
   - Полное ранжирование
   - Pairwise comparison matrix
   - Статистика голосов

### Шаг 4: Просмотреть список голосовавших

1. На странице результатов нажмите **"View Voters"**
2. Должны увидеть список пользователей с аватарами и датами голосования

### Шаг 5: Проверить URL

- Результаты: `https://beta.politsim.ru/ranked-polls/results/{POLL_ID}`
- Голосовавшие: `https://beta.politsim.ru/ranked-polls/voters/{POLL_ID}`

Замените `{POLL_ID}` на реальный ID опроса.

---

## ❓ Troubleshooting

### Проблема: Кнопка "View Results" не появляется

**Причина**: `canViewRankedResults()` возвращает false

**Решение**:
1. Проверить настройки видимости в metadata:
```sql
SELECT * FROM xf_alebarda_ranked_poll_metadata WHERE poll_id = YOUR_POLL_ID;
```

2. Проверить `results_visibility`:
   - `realtime` - всегда видны
   - `after_close` - после закрытия опроса

3. Если `after_close` - опрос должен быть закрыт (`close_date < NOW()`)

### Проблема: 404 при переходе на /ranked-polls/results/123

**Причина**: Роуты не импортированы

**Решение**:
```bash
php cmd.php xf-dev:import-routes
php cmd.php xf:rebuild-caches
```

### Проблема: Белая страница на странице результатов

**Причина**: PHP ошибка в контроллере или шаблоне

**Решение**:
```bash
# Проверить логи
tail -50 /var/www/u0513784/data/logs/error_log

# Проверить синтаксис
php -l src/addons/Alebarda/RankedPoll/Pub/Controller/RankedPoll.php
```

### Проблема: Pairwise matrix не отображается

**Причина**: Нет голосов или ошибка в алгоритме

**Решение**:
1. Проверить что есть голоса в БД:
```sql
SELECT COUNT(*) FROM xf_poll_ranked_vote WHERE poll_id = YOUR_POLL_ID;
```

2. Проверить работу Schulze:
```bash
php cmd.php xf-db:query "SELECT * FROM xf_poll_ranked_vote WHERE poll_id = YOUR_POLL_ID"
```

---

## 🎯 Полный workflow

1. ✅ **Backend расширения** (Repository, Entity, Service) - работают
2. ✅ **UI для создания** (Listener, template modification) - создано
3. ✅ **Контроллер результатов** (RankedPoll.php) - создан
4. ✅ **Шаблоны просмотра** (poll_results_ranked.html, poll_voters_ranked.html) - созданы
5. ✅ **Роуты** (ranked_poll_results, ranked_poll_voters) - зарегистрированы
6. ✅ **Кнопка View Results** - добавлена в poll_block_ranked.html

---

## 📊 Что показывает страница результатов

### Пример вывода:

```
Poll Results: What is your favorite programming language?

─────────────────────────────────
🏆 Winner: Python
─────────────────────────────────

Final Ranking:
#1 Python
#2 Rust
#3 JavaScript
#4 Go
#5 PHP

─────────────────────────────────
Pairwise Comparison Matrix

Each cell shows how many voters preferred the row candidate
over the column candidate.

         │ Python │ Rust │ JavaScript │ Go │ PHP │
─────────┼────────┼──────┼────────────┼────┼─────┤
Python   │   –    │  12  │     15     │ 18 │ 20  │
Rust     │   8    │  –   │     14     │ 16 │ 19  │
JavaScript│   5    │  6   │     –      │ 10 │ 14  │
Go       │   2    │  4   │      8     │ –  │ 12  │
PHP      │   0    │  1   │      6     │  8 │ –   │

Total Votes: 20

[View Voters]  [Back to Thread]
```

---

## ✨ Следующие возможные улучшения

1. **Графическая визуализация** - круговые диаграммы, bar charts
2. **Экспорт в CSV** - скачать результаты в файл
3. **История изменений голосов** - кто и когда менял свой голос
4. **Email уведомления** - автору опроса когда голосование завершено
5. **Детальная статистика** - распределение по рангам для каждого кандидата

---

Готово! 🚀 Все файлы созданы и готовы к загрузке.
