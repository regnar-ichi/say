<?php

$text = $text ?? '';
$tokens = $tokens ?? [];
$currentItem = $currentItem ?? null;
$readerTitle = $readerTitle ?? ($currentItem['item_title'] ?? '');
$error = $error ?? '';
$currentPage = !empty($currentItem) ? (int) ($currentItem['page_number'] ?? 1) : 1;
$pagesCount = !empty($currentItem) ? (int) ($currentItem['pages_count'] ?? 1) : 1;
$itemId = !empty($currentItem) ? (int) ($currentItem['item_id'] ?? 0) : 0;
$isReadingMode = !empty($currentItem);
$hasReaderPaper = !empty($tokens);

$renderReaderNav = static function (int $itemId, int $currentPage, int $pagesCount, array $currentItem): void {
    ?>
    <nav class="reader-book-nav" aria-label="Reader navigation">
        <a class="reader-book-back" href="/library">Back to Library</a>

        <div class="reader-book-title-block">
            <h1><?php echo htmlspecialchars($currentItem['item_title'] ?? 'Untitled text'); ?></h1>
            <div class="reader-page-tools">
                <span>Page <?php echo $currentPage; ?> of <?php echo $pagesCount; ?></span>
                <form method="get" action="/reader" class="reader-page-jump">
                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                    <input
                        type="number"
                        name="page"
                        min="1"
                        max="<?php echo $pagesCount; ?>"
                        value="<?php echo $currentPage; ?>"
                        aria-label="Page number"
                    >
                    <button type="submit">Go</button>
                </form>
            </div>
        </div>

        <div class="reader-book-pager">
            <?php if ($currentPage > 1): ?>
                <a class="btn-secondary" href="/reader?item_id=<?php echo $itemId; ?>&page=<?php echo $currentPage - 1; ?>">Prev</a>
            <?php else: ?>
                <span class="btn-secondary reader-page-disabled">Prev</span>
            <?php endif; ?>

            <?php if ($currentPage < $pagesCount): ?>
                <a class="btn-secondary" href="/reader?item_id=<?php echo $itemId; ?>&page=<?php echo $currentPage + 1; ?>">Next</a>
            <?php else: ?>
                <span class="btn-secondary reader-page-disabled">Next</span>
            <?php endif; ?>
        </div>
    </nav>
    <?php
};

$renderReaderSettings = static function (): void {
    ?>
    <div class="reader-settings" data-reader-settings>
        <button type="button" class="reader-settings-toggle" data-reader-settings-toggle aria-expanded="false">Aa</button>
        <div class="reader-settings-panel" data-reader-settings-panel>
            <div class="reader-settings-group" aria-label="Font size">
                <button type="button" data-reader-size="down">A-</button>
                <button type="button" data-reader-size="up">A+</button>
            </div>
            <div class="reader-settings-group" aria-label="Font family">
                <button type="button" data-reader-font="serif">Serif</button>
                <button type="button" data-reader-font="sans">Sans</button>
            </div>
            <div class="reader-settings-group" aria-label="Theme">
                <button type="button" data-reader-theme="light">Light</button>
                <button type="button" data-reader-theme="sepia">Sepia</button>
                <button type="button" data-reader-theme="dark">Dark</button>
            </div>
            <div class="reader-settings-group" aria-label="Text width">
                <button type="button" data-reader-width="narrow">Narrow</button>
                <button type="button" data-reader-width="normal">Normal</button>
                <button type="button" data-reader-width="wide">Wide</button>
            </div>
        </div>
    </div>
    <?php
};

$tooltipForMatches = static function (array $matches): string {
    $items = [];

    foreach ($matches as $match) {
        $lines = [];
        $lines[] = $match['text'] ?? '';

        if (!empty($match['transcription'])) {
            $lines[] = $match['transcription'];
        }

        if (!empty($match['translate'])) {
            $lines[] = $match['translate'];
        }

        $meta = implode(' · ', array_filter([
            $match['type'] ?? '',
            $match['level'] ?? '',
        ]));

        if ($meta !== '') {
            $lines[] = $meta;
        }

        if (!empty($match['example'])) {
            $lines[] = 'EN: ' . $match['example'];
        }

        if (!empty($match['example_ru'])) {
            $lines[] = 'RU: ' . $match['example_ru'];
        }

        if (!empty($match['memory_hint'])) {
            $lines[] = 'Hint: ' . $match['memory_hint'];
        }

        $items[] = implode("\n", $lines);
    }

    return implode("\n\n", $items);
};

$meaningsForMatches = static function (array $matches): array {
    $meanings = [];

    foreach ($matches as $match) {
        $meaning = array_filter([
            'text' => trim((string) ($match['text'] ?? '')),
            'translation' => trim((string) ($match['translate'] ?? '')),
            'type' => trim((string) ($match['type'] ?? '')),
            'transcription' => trim((string) ($match['transcription'] ?? '')),
            'example' => trim((string) ($match['example'] ?? '')),
            'example_ru' => trim((string) ($match['example_ru'] ?? '')),
        ], static fn(string $value): bool => $value !== '');

        if (!empty($meaning)) {
            $meanings[] = $meaning;
        }
    }

    return $meanings;
};

$readerHtml = '';

foreach ($tokens as $token) {
    if (!empty($token['matches'])) {
        $meaningsJson = json_encode($meaningsForMatches($token['matches']), JSON_UNESCAPED_UNICODE);

        $readerHtml .= '<span class="reader-word" tabindex="0" data-meanings="'
            . htmlspecialchars($meaningsJson !== false ? $meaningsJson : '[]', ENT_QUOTES, 'UTF-8')
            . '">'
            . htmlspecialchars($token['text'])
            . '</span>';
    } else {
        $readerHtml .= htmlspecialchars($token['text']);
    }
}

?>

<div
    class="reader-page-shell"
    data-reader-root
    data-reader-font="serif"
    data-reader-theme="light"
    data-reader-width="normal"
    data-reader-size="2"
>
<?php if ($hasReaderPaper): ?>
    <?php $renderReaderSettings(); ?>
<?php endif; ?>

<?php if ($isReadingMode): ?>
    <section class="reader-book-shell reader-reading-mode">
        <?php $renderReaderNav($itemId, $currentPage, $pagesCount, $currentItem); ?>

        <article class="reader-paper">
            <?php echo $readerHtml; ?>
        </article>

        <?php $renderReaderNav($itemId, $currentPage, $pagesCount, $currentItem); ?>
    </section>
<?php else: ?>
    <section class="reader-empty-state">
        <div>
            <h1>Choose a text/book from Library</h1>
            <p>Saved texts and FB2 books open here in Reader.</p>
        </div>
        <a href="/library" class="btn-primary reader-link-button">Open Library</a>
    </section>
<?php endif; ?>
</div>

<script src="/assets/js/reader.js"></script>
