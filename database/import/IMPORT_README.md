# Импорт слов

Документ фиксирует текущую логику импорта слов в проекте: как готовить TSV, как запускать dry-run, как импортировать новые слова и как безопасно обновлять уже загруженные поля.

Главная текущая стратегия: новые партии слов делаем по Oxford-маршруту, а не “из головы”.

---

## Основные файлы

Основной импортёр:

```text
database/import/import_tsv.php
```

Папка для импортных TSV-партий:

```text
database/import/batches/
```

Папка для source-файлов Oxford:

```text
database/import/sources/
```

Главный маршрут для новых Oxford-партий:

```text
database/import/sources/oxford_core_missing.tsv
```

Файл прогресса Oxford-импорта:

```text
database/import/sources/oxford_import_progress.md
```

Старый импортёр:

```text
database/import/import_words_100.php
```

`import_words_100.php` считается legacy. Для новых партий слов его не использовать.

Правила для будущего обновления `memory_hint`:

```text
database/import/MEMORY_HINT_RULES.md
```

---

## Source-файлы Oxford

В папке:

```text
database/import/sources/
```

лежат основные source-файлы:

```text
oxford3000_raw.tsv
oxford5000_raw.tsv
oxford_core_raw.tsv
oxford_core_missing.tsv
oxford_import_progress.md
```

Назначение:

```text
oxford3000_raw.tsv       — извлечённый Oxford 3000
oxford5000_raw.tsv       — извлечённый Oxford 5000
oxford_core_raw.tsv      — объединённый Oxford-маршрут
oxford_core_missing.tsv  — Oxford-записи, которых ещё нет в translate
oxford_import_progress.md — фиксация уже импортированных строк
```

`oxford_core_missing.tsv` используем как фиксированный маршрут. После каждого импорта его не пересоздаём.

Новые партии берём по номерам строк из `oxford_import_progress.md`.

Строка 1 = первая строка данных после заголовка.

---

## Текущая стратегия импорта

Сейчас база собирается по Oxford 3000/5000.

Правила:

```text
идти строго по oxford_core_missing.tsv сверху вниз;
не сортировать по level;
не придумывать новые слова вручную;
одна запись word + pos = одна карточка;
source брать из oxford_core_missing.tsv: oxford3000 или oxford5000;
source_key делать <source>:<word>:<pos>:1;
memory_hint оставлять пустым;
служебные слова A1 не пропускать;
determiner/pronoun/number/exclamation импортировать как обычные type.
```

Пример:

```text
source = oxford3000
source_key = oxford3000:above:adverb:1
text = above
type = adverb
level = A1
```

Oxford stage — это базовый словарь. Одно слово + одна часть речи = одна карточка.

Не дробить одно слово на несколько значений по EVP guideword на этом этапе.

Например сейчас:

```text
above
adverb
выше; наверху; выше по тексту
```

Позже значения EVP можно хранить отдельно, например в будущей таблице `word_meanings`.

Фразы типа `above all` позже импортировать как отдельные записи:

```text
above all
phrase
прежде всего; самое главное
```

---

## Где хранить TSV-файлы

Все рабочие партии слов хранить в:

```text
database/import/batches/
```

Примеры:

```text
database/import/batches/oxford_batch_012_100.tsv
database/import/batches/oxford_batch_013_100.tsv
```

TSV-файлы после импорта не удалять. Они являются исходниками словаря.

Если была создана очищенная копия без дублей, исходный файл тоже не удалять.

---

## Формат TSV

Текущий основной формат:

```text
source
source_key
text
translate
type
level
transcription
example
example_ru
memory_hint
topics
forms
```

Пример строки:

```text
oxford3000	oxford3000:above:adverb:1	above	выше; наверху; выше по тексту	adverb	A1	[əˈbʌv]	See the example above.	Смотри пример выше.		place,communication	
```

---

## Обязательные поля

Для новых импортов обязательны:

```text
source
source_key
text
translate
```

Желательные поля:

```text
type
level
transcription
example
example_ru
topics
forms
```

На этапе массового Oxford-импорта поле:

```text
memory_hint
```

оставлять пустым.

`visible` и `visible_ru` в TSV не используются. Импортёр для новых слов ставит их сам.

---

## Как готовить новую Oxford-партию

Перед созданием новой партии открыть:

```text
database/import/sources/oxford_import_progress.md
```

и посмотреть раздел `Next`.

Например:

```text
oxford_batch_014_100.tsv | 201-300
```

Значит нужно взять строки 201-300 из:

```text
database/import/sources/oxford_core_missing.tsv
```

Из `oxford_core_missing.tsv` брать:

```text
word
pos
level
source_order
source
```

В импортный TSV добавить самостоятельно:

```text
translate
transcription
example
example_ru
topics
forms
```

`memory_hint` оставить пустым.

---

## Правила качества для TSV

Перед dry-run проверить:

```text
количество строк данных без заголовка;
first word;
first type;
last word;
last type;
empty_transcription = 0;
non_empty_memory_hint = 0 для Oxford-партий;
нет дублей text + type внутри файла;
forms выглядят нормально.
```

Формы нужны для Reader, чтобы он мог находить слово по производным формам.

Примеры forms:

```text
bathroom -> bathrooms
be -> am,is,are,was,were,been,being
child -> children
go -> goes,went,gone,going
choose -> chooses,chose,chosen,choosing
```

Не добавлять само базовое слово в `forms`, если оно уже находится по `text`.

---

## source и source_key

`source` — источник или логическая группа слов.

Для новых Oxford-партий использовать:

```text
oxford3000
oxford5000
```

`source_key` — стабильный ключ конкретной записи.

Формат для Oxford:

```text
<source>:<word>:<pos>:1
```

Примеры:

```text
oxford3000:above:adverb:1
oxford3000:be:verb:1
oxford5000:dispute:noun:1
```

Одно английское слово может иметь несколько частей речи. Это разные записи.

Например:

```text
dance noun
dance verb
```

Они должны иметь разные `source_key`.

---

## Как импортёр определяет дубль

Основной способ:

```text
source + source_key
```

Fallback:

```text
LOWER(text) + LOWER(type) + LOWER(translate)
```

Если `skipped_existing_words > 0`, это не ошибка, но для текущего процесса нужно сделать очищенную копию TSV без дублей.

Важно: старые слова могли быть импортированы с `source = core5000`. Поэтому `oxford_core_missing.tsv` уже был создан сравнением по:

```text
LOWER(word) + normalized pos
```

против:

```text
LOWER(translate.text) + normalized translate.type
```

То есть если слово уже было в базе как `core5000`, оно обычно не должно попадать в `oxford_core_missing.tsv`.

---

## topics

В TSV темы пишутся в одной ячейке через запятую:

```text
education,work,daily life
```

Импортёр:

```text
создаёт topic, если его ещё нет;
создаёт связь word_topics;
не удаляет старые topics;
не удаляет старые связи word_topics.
```

В режиме `update_selected_fields` topics не обрабатываются.

---

## forms

В TSV формы слова пишутся в одной ячейке через запятую:

```text
achieves,achieved,achieving
```

Для неправильных форм:

```text
go	goes,went,gone,going
choose	chooses,chose,chosen,choosing
child	children
```

Импортёр:

```text
добавляет формы в word_forms;
не удаляет старые формы;
не обрабатывает forms в режиме update_selected_fields.
```

---

# Режимы импорта

## 1. --mode=dry-run

Используется для проверки добавления новых слов.

Команда Windows/OpenServer:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/<file>.tsv --mode=dry-run
```

Что делает:

```text
читает TSV;
проверяет строки;
проверяет дубли;
считает, сколько слов было бы добавлено;
считает, сколько topics/forms было бы создано;
ничего не пишет в базу.
```

Ожидаемые поля отчёта:

```text
rows
valid_rows
would_insert_words
skipped_existing_words
would_create_topics
would_link_topics
would_add_forms
errors
```

База при `--mode=dry-run` не меняется.

---

## 2. --mode=create_new_only

Используется для реального добавления новых слов.

Команда:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/<final_file>.tsv --mode=create_new_only
```

Что делает:

```text
создаёт только новые слова;
существующие слова не обновляет;
создаёт новые topics;
создаёт связи word_topics;
добавляет forms;
ставит visible = 1;
ставит visible_ru = 1;
заполняет source, source_key, import_batch, import_hash, imported_at, import_updated_at.
```

Что НЕ делает:

```text
не обновляет существующие слова;
не перезаписывает memory_hint у старых слов;
не удаляет topics;
не удаляет forms;
не чистит word_topics;
не меняет статистику, tests, examination.
```

Ожидаемые поля отчёта:

```text
rows
valid_rows
inserted_words
skipped_existing_words
topics_created
word_topics_created
forms_created
errors
```

Перед `create_new_only` всегда сначала запускать `--mode=dry-run`.

Реальный импорт запускать только после явного разрешения пользователя.

---

## 3. --mode=update_selected_fields

Используется для безопасного обновления уже загруженных слов.

Сейчас этот режим обновляет только:

```text
memory_hint
import_updated_at
```

Новые слова в этом режиме не создаются.

Topics и forms в этом режиме не обрабатываются.

Остальные поля `translate` не обновляются:

```text
text
translate
type
level
transcription
example
example_ru
source
source_key
import_batch
import_hash
imported_at
visible
visible_ru
```

### Dry-run обновления memory_hint

Команда:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/hints/<file>.tsv --mode=update_selected_fields --dry-run=1
```

Что делает:

```text
читает TSV;
ищет существующие слова;
считает, сколько memory_hint было бы обновлено;
ничего не пишет в базу.
```

Ожидаемые поля отчёта:

```text
rows
valid_rows
would_update_memory_hint
updated_memory_hint
skipped_not_found
skipped_empty_memory_hint
errors
```

При dry-run должно быть:

```text
updated_memory_hint=0
```

### Реальное обновление memory_hint

Команда:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/hints/<file>.tsv --mode=update_selected_fields
```

Что делает:

```text
ищет существующее слово;
если слово найдено и memory_hint не пустой — обновляет memory_hint;
обновляет import_updated_at;
новые слова не создаёт.
```

Ожидаемый отчёт:

```text
updated_memory_hint=количество обновленных подсказок
errors=0
```

---

# Безопасный порядок работы

## Для добавления новой Oxford-партии

1. Открыть:

```text
database/import/sources/oxford_import_progress.md
```

2. Взять следующий диапазон строк из:

```text
database/import/sources/oxford_core_missing.tsv
```

3. Создать TSV в формате импорта.

4. Положить TSV в:

```text
database/import/batches/
```

5. Проверить TSV:

```text
строк данных без заголовка;
first word/type;
last word/type;
empty_transcription = 0;
non_empty_memory_hint = 0;
нет дублей text + type внутри файла.
```

6. Запустить dry-run:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/<file>.tsv --mode=dry-run
```

7. Проверить отчёт:

```text
errors=0
valid_rows соответствует ожидаемому количеству
skipped_existing_words
would_insert_words
would_create_topics
would_add_forms
```

8. Если `skipped_existing_words > 0`, создать очищенную копию TSV без дублей и снова запустить dry-run.

9. Если всё правильно, ждать явного разрешения пользователя на реальный импорт.

10. После разрешения запустить:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/<final_file>.tsv --mode=create_new_only
```

11. После импорта проверить:

```text
новое количество слов в translate;
несколько новых слов найдены в базе;
Reader находит несколько forms из TSV.
```

12. После успешного импорта обновить:

```text
database/import/sources/oxford_import_progress.md
```

---

## Автоочистка дублей

Если dry-run показывает:

```text
skipped_existing_words > 0
```

нужно:

```text
найти строки-дубли по логике импортёра;
создать новый TSV-файл без дублей;
назвать файл по реальному количеству строк;
исходный файл не удалять;
БД не менять;
запустить dry-run для очищенного файла.
```

Примеры:

```text
oxford_batch_013_100.tsv -> oxford_batch_013_097.tsv
core5000_batch_010_100.tsv -> core5000_batch_010_081.tsv
```

---

## Для обновления memory_hint у существующих слов

1. Подготовить TSV с новыми `memory_hint`.

2. Положить файл в:

```text
database/import/batches/hints/
```

3. Запустить dry-run:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/hints/<file>.tsv --mode=update_selected_fields --dry-run=1
```

4. Проверить отчёт:

```text
errors=0
would_update_memory_hint
skipped_not_found=0
skipped_empty_memory_hint=0
updated_memory_hint=0
```

5. Если всё правильно, запустить реальное обновление:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/hints/<file>.tsv --mode=update_selected_fields
```

6. После обновления проверить несколько слов на сайте.

---

# Что проверять до реального импорта

Перед любым реальным импортом:

```text
errors должно быть 0;
valid_rows должно совпадать с ожидаемым количеством строк;
skipped_existing_words нужно проверить;
skipped_not_found должно быть 0 для обновления memory_hint;
skipped_empty_memory_hint должно быть 0, если обновляем все подсказки.
```

Если есть ошибки — реальный импорт не запускать.

---

# Что проверять после импорта

Для новых слов:

```text
количество слов в translate;
появились ли новые слова в базе;
показываются ли level, transcription, topics;
работает ли Reader по forms;
нет ли дублей.
```

Для обновления `memory_hint`:

```text
у выбранных слов изменился memory_hint;
обновился import_updated_at;
не изменились text, translate, type, level, transcription, example, example_ru;
количество строк в translate не изменилось;
количество topics, word_topics, word_forms не изменилось.
```

---

# Правила безопасности

1. Всегда сначала запускать dry-run.

2. Реальный импорт запускать только после явного разрешения пользователя.

3. Не удалять слова ради перегенерации.

4. Не удалять исходные TSV.

5. Существующие слова обновлять только через:

```text
--mode=update_selected_fields
```

6. Сейчас `update_selected_fields` разрешён только для `memory_hint`.

7. Не добавлять `force_update` без отдельного плана.

8. Перед большими изменениями делать backup базы.

9. Не менять Reader, тесты, аккаунт, визуал в задачах импорта слов.

10. Не скачивать EVP или аудио без отдельной задачи.

---

# Текущий статус

База уже содержит старые партии слов и новые Oxford-партии.

Старые слова удалять не нужно.

Новые Oxford-слова добавлять только из:

```text
database/import/sources/oxford_core_missing.tsv
```

Прогресс смотреть в:

```text
database/import/sources/oxford_import_progress.md
```

---

# Связанные документы

Короткая инструкция для Codex:

```text
database/import/CODEX_IMPORT_QUICK.md
```

Правила генерации поля `memory_hint`:

```text
database/import/MEMORY_HINT_RULES.md
```

Полная инструкция по импорту:

```text
database/import/IMPORT_README.md
```