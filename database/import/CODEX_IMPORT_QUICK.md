# CODEX_IMPORT_QUICK.md

Короткий регламент для Codex по импорту слов.

Полная инструкция:

```text
database/import/IMPORT_README.md
```

---

## Главные файлы

Основной импортёр:

```text
database/import/import_tsv.php
```

Папка импортных TSV:

```text
database/import/batches/
```

Source-файлы Oxford:

```text
database/import/sources/
```

Маршрут Oxford:

```text
database/import/sources/oxford_core_missing.tsv
```

Прогресс Oxford:

```text
database/import/sources/oxford_import_progress.md
```

Legacy-импортёр не использовать:

```text
database/import/import_words_100.php
```

---

## Oxford route

Новые Oxford-партии брать только из:

```text
database/import/sources/oxford_core_missing.tsv
```

Перед созданием новой партии обязательно открыть:

```text
database/import/sources/oxford_import_progress.md
```

Следующий batch брать по строкам из раздела `Next`.

Правила:

```text
идти строго по указанным строкам;
не сортировать по level;
не придумывать слова вручную;
одна запись word + pos = одна карточка;
source брать из oxford_core_missing.tsv: oxford3000/oxford5000;
source_key делать <source>:<word>:<pos>:1;
memory_hint оставлять пустым;
служебные слова A1 не пропускать;
determiner/pronoun/number/exclamation импортировать как обычные type.
```

---

## Формат импортного TSV

Колонки:

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

Обязательные поля:

```text
source
source_key
text
translate
```

Для Oxford-партий обязательно:

```text
transcription не пустой;
example не пустой;
example_ru не пустой;
memory_hint пустой.
```

Пример:

```text
oxford3000	oxford3000:above:adverb:1	above	выше; наверху; выше по тексту	adverb	A1	[əˈbʌv]	See the example above.	Смотри пример выше.		place,communication	
```

---

## Проверка TSV перед dry-run

Перед dry-run проверить:

```text
строк данных без заголовка;
first word;
first type;
last word;
last type;
empty_transcription;
non_empty_memory_hint;
дубли text + type внутри файла;
подозрительные forms.
```

Для Oxford-партий должно быть:

```text
empty_transcription = 0
non_empty_memory_hint = 0
```

---

## Dry-run

Перед любым импортом обязательно запускать:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/<file>.tsv --mode=dry-run
```

БД на dry-run не менять.

После dry-run вывести:

```text
финальный файл для импорта
rows
valid_rows
would_insert_words
skipped_existing_words
would_create_topics
would_link_topics
would_add_forms
errors
```

Если `errors > 0`, реальный импорт не запускать.

---

## Дубли после dry-run

Импортёр определяет дубль так:

```text
1. source + source_key
2. fallback: LOWER(text) + LOWER(type) + LOWER(translate)
```

Если dry-run показывает:

```text
skipped_existing_words > 0
```

Codex должен без дополнительного вопроса:

```text
найти строки-дубли;
создать очищенную копию TSV без дублей;
назвать файл по реальному количеству строк;
исходный файл не удалять;
БД не менять;
запустить dry-run для очищенного файла;
в отчёте указать финальный файл.
```

Пример:

```text
oxford_batch_013_100.tsv -> oxford_batch_013_097.tsv
```

---

## Реальный импорт

Реальный импорт запускать только после явного разрешения пользователя.

Команда:

```powershell
& 'C:\OSPanel\modules\PHP-8.3\php.exe' database\import\import_tsv.php --file=database/import/batches/<final_file>.tsv --mode=create_new_only
```

Важно:

```text
использовать только финальный файл после dry-run;
если была очищенная копия — импортировать её;
исходный файл с дублями не импортировать;
существующие слова не обновлять;
Reader, тесты, аккаунт, визуал не трогать.
```

После импорта вывести:

```text
rows
valid_rows
inserted_words
skipped_existing_words
topics_created
word_topics_created
forms_created
errors
translate_count
```

Потом проверить:

```text
несколько новых слов найдены в базе;
Reader находит несколько forms из TSV.
```

---

## Обновление прогресса

После успешного реального импорта обновить:

```text
database/import/sources/oxford_import_progress.md
```

Добавить импортированную партию в `Imported`.

Обновить `Next` на следующий диапазон строк.

Пример:

```text
| oxford_batch_013_100.tsv | 101-200 | any / pronoun | enough / pronoun | 100 | 1499 | OK |
```

---

## memory_hint

Для массового Oxford-импорта:

```text
memory_hint оставлять пустым
```

Hints обновлять позже отдельными TSV через:

```text
--mode=update_selected_fields
```

В задачах обычного Oxford-импорта hint-файлы не трогать.

---

## Запрещено

Нельзя:

```text
запускать реальный импорт без dry-run;
запускать реальный импорт без разрешения пользователя;
удалять исходные TSV;
использовать import_words_100.php;
делать force update;
обновлять существующие слова в create_new_only;
менять структуру БД без отдельного плана;
трогать Reader, тесты, аккаунт, визуал;
пересоздавать oxford_core_missing.tsv после каждого импорта;
скачивать EVP или аудио без отдельной задачи.
```