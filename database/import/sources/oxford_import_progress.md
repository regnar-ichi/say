# Oxford import progress

Route source:

database/import/sources/oxford_core_missing.tsv

Правило:
- oxford_core_missing.tsv используем как фиксированный маршрут;
- после каждого импорта не пересоздаём missing;
- следующие партии берём по номерам строк из этого файла;
- строка 1 = первая строка данных после заголовка.

Imported:

| Batch | Source rows | First | Last | Inserted | translate_count | Status |
|---|---:|---|---|---:|---:|---|
| oxford_batch_012_100.tsv | 1-100 | bathroom / noun | any / determiner | 100 | 1399 | OK |
| oxford_batch_014_099.tsv | 201-300 | enough / adverb | favourite / noun | 99 | 1598 | OK |
| oxford_batch_015_100.tsv | 301-400 | keep / verb | home / adverb | 100 | 1698 | OK |

Next:

| Batch | Source rows | Start after |
|---|---:|---|
| oxford_batch_016_100.tsv | 401-500 | home / adjective |
