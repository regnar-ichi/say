<?php

$items = $items ?? [];
$error = $error ?? '';
$manualTitle = $manualTitle ?? '';
$manualText = $manualText ?? '';

$typeLabel = static function (string $type): string {
    return match ($type) {
        'fb2' => 'FB2',
        default => 'Text',
    };
};

$coverClass = static function (string $type): string {
    return $type === 'fb2' ? 'is-fb2' : 'is-text';
};

?>

<section class="library-panel">
    <div class="library-header">
        <h1>Library</h1>
        <a href="/reader" class="btn-primary library-new-link">New text</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="library-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" action="/library/save-text" class="library-add-text-form">
        <div class="library-section-heading">
            <h2>Add text</h2>
            <span>Paste plain text and save it to Reader Library.</span>
        </div>

        <input
            type="text"
            name="reader_title"
            class="library-text-input"
            placeholder="Title for saving..."
            value="<?php echo htmlspecialchars($manualTitle); ?>"
        >

        <textarea
            name="reader_text"
            class="library-textarea"
            placeholder="Paste English text here..."
        ><?php echo htmlspecialchars($manualText); ?></textarea>

        <div class="library-form-actions">
            <button type="submit" class="btn-primary">Save to Library</button>
        </div>
    </form>

    <form method="post" action="/library/upload-fb2" enctype="multipart/form-data" class="library-upload-form">
        <label class="library-upload-label" for="fb2-file">Upload FB2 book</label>
        <div class="library-upload-controls">
            <input id="fb2-file" type="file" name="fb2_file" accept=".fb2" class="library-file-input">
            <button type="submit" class="btn-secondary">Upload FB2</button>
        </div>
    </form>

    <section class="library-url-placeholder" aria-label="Future URL import">
        <div>
            <strong>URL import</strong>
            <span>Coming later: save a web page and read it inside Reader.</span>
        </div>
    </section>

    <?php if (empty($items)): ?>
        <div class="library-empty">
            Saved texts will appear here.
        </div>
    <?php else: ?>
        <div class="library-list">
            <?php foreach ($items as $item): ?>
                <?php
                    $pagesCount = max(1, (int) ($item['pages_count'] ?? 1));
                    $progressPage = (int) ($item['progress_page_number'] ?? 0);
                    $openPage = $progressPage > 0 && $progressPage <= $pagesCount ? $progressPage : 1;
                    $hasProgress = $progressPage > 0 && $progressPage <= $pagesCount;
                    $coverPath = (string) ($item['cover_path'] ?? '');
                    $hasCover = ($item['source_type'] ?? 'manual_text') === 'fb2' && $coverPath !== '';
                ?>
                <article class="library-item">
                    <div class="library-cover <?php echo htmlspecialchars($coverClass($item['source_type'] ?? 'manual_text')); ?><?php echo $hasCover ? ' has-image' : ''; ?>">
                        <?php if ($hasCover): ?>
                            <img src="<?php echo htmlspecialchars($coverPath); ?>" alt="<?php echo htmlspecialchars($item['title'] ?? 'Book cover'); ?>">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($typeLabel($item['source_type'] ?? 'manual_text')); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="library-item-main">
                        <h2><?php echo htmlspecialchars($item['title'] ?? 'Untitled text'); ?></h2>
                        <div class="library-item-meta">
                            <span><?php echo htmlspecialchars($typeLabel($item['source_type'] ?? 'manual_text')); ?></span>
                            <span><?php echo (int) ($item['pages_count'] ?? 0); ?> page(s)</span>
                            <span><?php echo htmlspecialchars($item['updated_at'] ?? ''); ?></span>
                        </div>
                        <?php if ($hasProgress): ?>
                            <div class="library-item-progress">
                                Continue page <?php echo $openPage; ?> of <?php echo $pagesCount; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['source_filename'])): ?>
                            <div class="library-item-filename">
                                <?php echo htmlspecialchars($item['source_filename']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="library-item-actions">
                        <a
                            href="/reader?item_id=<?php echo (int) $item['id']; ?>&page=<?php echo $openPage; ?>"
                            class="btn-primary library-open-link"
                        >Open</a>

                        <form method="post" action="/library/delete" class="library-delete-form">
                            <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="btn-secondary">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
