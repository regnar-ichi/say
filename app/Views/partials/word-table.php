<?php

$words = $words ?? [];
$currentType = $currentType ?? '';
$currentLevel = $currentLevel ?? '';
$currentTopic = $currentTopic ?? '';
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
$totalCount = $totalCount ?? 0;
$limit = $limit ?? 25;
$levels = $levels ?? [];
$types = $types ?? [];
$topics = $topics ?? [];

$successPercent = static function (int $right, int $wrong): int {
    $total = $right + $wrong;

    if ($total === 0) {
        return 0;
    }

    return (int)round(($right / $total) * 100);
};

$percentClass = static function (int $percent): string {
    if ($percent >= 81) {
        return 'is-excellent';
    }

    if ($percent >= 61) {
        return 'is-good';
    }

    if ($percent >= 41) {
        return 'is-mid';
    }

    if ($percent >= 21) {
        return 'is-low';
    }

    return 'is-bad';
};

$wordsPageUrl = static function (array $params = []): string {
    $params = array_filter($params, static fn($value) => $value !== '' && $value !== null);
    $query = http_build_query($params);

    return '/words' . ($query !== '' ? '?' . $query : '');
};

?>

<div class="word-filter words-toolbar">
    <div class="words-toolbar-left">
        <select id="typeFilter" class="type-filter" onchange="const params = new URLSearchParams(); if (this.value) params.set('type', this.value); const level = document.getElementById('levelFilter').value; if (level) params.set('level', level); const topic = document.getElementById('topicFilter').value; if (topic) params.set('topic', topic); window.location.href = params.toString() ? '/words?' + params.toString() : '/words';">
            <option value="">All</option>
            <option value="noun" <?php echo $currentType === 'noun' ? 'selected' : ''; ?>>noun</option>
            <option value="verb" <?php echo $currentType === 'verb' ? 'selected' : ''; ?>>verb</option>
            <option value="adjective" <?php echo $currentType === 'adjective' ? 'selected' : ''; ?>>adjective</option>
            <option value="adverb" <?php echo $currentType === 'adverb' ? 'selected' : ''; ?>>adverb</option>
            <option value="phrase" <?php echo $currentType === 'phrase' ? 'selected' : ''; ?>>phrase</option>
            <option value="idiom" <?php echo $currentType === 'idiom' ? 'selected' : ''; ?>>idiom</option>
            <option value="proverb" <?php echo $currentType === 'proverb' ? 'selected' : ''; ?>>proverb</option>
            <option value="slang" <?php echo $currentType === 'slang' ? 'selected' : ''; ?>>slang</option>
            <option value="joke" <?php echo $currentType === 'joke' ? 'selected' : ''; ?>>joke</option>
        </select>
        <select id="levelFilter" class="type-filter" onchange="const params = new URLSearchParams(); const type = document.getElementById('typeFilter').value; if (type) params.set('type', type); if (this.value) params.set('level', this.value); const topic = document.getElementById('topicFilter').value; if (topic) params.set('topic', topic); window.location.href = params.toString() ? '/words?' + params.toString() : '/words';">
            <option value="">All levels</option>
            <?php foreach ($levels as $level): ?>
                <option value="<?php echo htmlspecialchars($level); ?>" <?php echo $currentLevel === $level ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($level); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select id="topicFilter" class="type-filter" onchange="const params = new URLSearchParams(); const type = document.getElementById('typeFilter').value; if (type) params.set('type', type); const level = document.getElementById('levelFilter').value; if (level) params.set('level', level); if (this.value) params.set('topic', this.value); window.location.href = params.toString() ? '/words?' + params.toString() : '/words';">
            <option value="">All topics</option>
            <?php foreach ($topics as $topic): ?>
                <option value="<?php echo htmlspecialchars($topic['slug']); ?>" <?php echo $currentTopic === $topic['slug'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($topic['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button id="addWordBtn" class="toolbar-icon-btn add_word" type="button" title="Add word" aria-label="Add word">+</button>
        <button id="btn-start-test" class="toolbar-icon-btn" type="button" title="Start test" aria-label="Start test">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 11l2 2 4-5" />
                <path d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                <path d="M9 4.2V3h6v1.2" />
            </svg>
        </button>
        <button id="word-card-start-button" class="toolbar-icon-btn" type="button" title="Words" aria-label="Words">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 4h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20H5a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 5 4Z" />
                <path d="M7.5 8h9" />
                <path d="M7.5 12h5.5" />
                <path d="M7.5 16h7" />
            </svg>
        </button>
        <button class="toolbar-icon-btn is-placeholder" type="button" title="AI tools" aria-label="AI tools" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3l1.4 4.2L18 8.6l-4.6 1.4L12 15l-1.4-5L6 8.6l4.6-1.4L12 3Z" />
                <path d="M5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8L5 14Z" />
                <path d="M18 15l.7 1.8 1.8.7-1.8.7L18 21l-.7-1.8-1.8-.7 1.8-.7L18 15Z" />
            </svg>
        </button>
    </div>

    <div class="words-toolbar-right">
        <div class="top-search search-container">
            <input
                type="text"
                id="search-input"
                name="search"
                class="search-input"
                placeholder="Search words, examples..."
                autocomplete="off"
            >
            <div id="search-results" class="search-results"></div>
        </div>
    </div>
</div>

<div class="words-paw-divider" aria-hidden="true"></div>

<div class="words test-stat-words">
    <?php foreach ($words as $word): ?>
        <?php
            $right = (int)($word['right_count'] ?? 0);
            $wrong = (int)($word['wrong_count'] ?? 0);
            $rightRu = (int)($word['right_ru_count'] ?? 0);
            $wrongRu = (int)($word['wrong_ru_count'] ?? 0);
            $enPercent = $successPercent($right, $wrong);
            $ruPercent = $successPercent($rightRu, $wrongRu);
            $isVisible = ((int)($word['visible'] ?? 0) === 1) || ((int)($word['visible_ru'] ?? 0) === 1);
        ?>

        <div class="word-card test-stat-card">
            <div class="word-card-header">
                <div class="word-id">
                    #<?php echo htmlspecialchars($word['id']); ?>
                </div>

                <div class="word-actions test-stat-actions">
                    <form method="post" action="/ajax.php">
                        <input type="hidden" name="action" value="word_toggle_visibility">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($word['id']); ?>">
                        <button
                            type="submit"
                            class="btn-icon-word btn-visibility-word <?php echo $isVisible ? 'is-visible' : 'is-hidden'; ?>"
                            title="<?php echo $isVisible ? 'Visible in tests' : 'Hidden in tests'; ?>"
                            aria-label="<?php echo $isVisible ? 'Visible in tests' : 'Hidden in tests'; ?>"
                        >
                            <svg class="test-card-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3.4 12s3.1-5 8.6-5 8.6 5 8.6 5-3.1 5-8.6 5-8.6-5-8.6-5Z" />
                                <circle cx="12" cy="12" r="2.6" />
                            </svg>
                        </button>
                    </form>

                    <button
                        type="button"
                        class="btn-icon-word btn-edit-word"
                        data-id="<?php echo htmlspecialchars($word['id']); ?>"
                        data-text="<?php echo htmlspecialchars($word['text']); ?>"
                        data-translate="<?php echo htmlspecialchars($word['translate']); ?>"
                        data-type="<?php echo htmlspecialchars($word['type'] ?? ''); ?>"
                        data-example="<?php echo htmlspecialchars($word['example'] ?? ''); ?>"
                        data-example-ru="<?php echo htmlspecialchars($word['example_ru'] ?? ''); ?>"
                        title="Edit"
                        aria-label="Edit"
                    >
                        <svg class="test-card-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 17.7 5.8 14 15.9 3.9a2 2 0 0 1 2.8 0l1.4 1.4a2 2 0 0 1 0 2.8L10 18.2 6.3 19 5 17.7Z" />
                            <path d="m14.2 5.6 4.2 4.2" />
                            <path d="M5 21h14" />
                        </svg>
                    </button>

                    <form method="post" action="/ajax.php" onsubmit="return confirm('Delete this word?');">
                        <input type="hidden" name="action" value="word_delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($word['id']); ?>">
                        <button type="submit" class="btn-icon-word btn-delete-word" title="Delete" aria-label="Delete">
                            <svg class="test-card-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 8h8l-.7 11.2a2 2 0 0 1-2 1.8h-2.6a2 2 0 0 1-2-1.8L8 8Z" />
                                <path d="M6.5 8h11" />
                                <path d="M9.5 8l.6-2.1A1.2 1.2 0 0 1 11.3 5h1.4a1.2 1.2 0 0 1 1.2.9l.6 2.1" />
                                <path d="M11 11v6" />
                                <path d="M13 11v6" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="test-stat-body">
                <div class="test-card-main">
                    <div class="test-card-terms">
                        <div class="word-text test-stat-word-text">
                            <?php echo htmlspecialchars($word['text']); ?>
                        </div>

                        <?php if (!empty($word['transcription'])): ?>
                            <div class="test-stat-transcription">
                                <?php echo htmlspecialchars($word['transcription']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="word-translate test-stat-translate">
                            <?php echo htmlspecialchars($word['translate']); ?>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="word-sound-icon"
                        title="Play pronunciation"
                        aria-label="Play pronunciation"
                        data-speech-text="<?php echo htmlspecialchars($word['text']); ?>"
                    >
                        <span class="sound-play-icon" aria-hidden="true"></span>
                        <span class="sound-wave-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </button>
                </div>

                <div class="test-tag-row">
                    <?php if (!empty($word['type']) || !empty($word['level'])): ?>
                        <div class="test-word-meta">
                            <?php
                                echo htmlspecialchars(implode(' · ', array_filter([
                                    $word['type'] ?? '',
                                    $word['level'] ?? '',
                                ])));
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($word['topics'])): ?>
                        <div class="test-word-topics">
                            <?php echo htmlspecialchars($word['topics']); ?>
                        </div>
                    <?php endif; ?>

                    <span class="test-tag test-tag-lang">EN</span>
                    <span class="test-tag test-tag-lang">RU</span>

                    <?php if (!empty($word['memory_hint'])): ?>
                        <span
                            class="test-tag test-tag-example"
                            title="<?php echo htmlspecialchars($word['memory_hint']); ?>"
                            data-tooltip="<?php echo htmlspecialchars($word['memory_hint']); ?>"
                        >Hint</span>
                    <?php endif; ?>

                    <?php if (!empty($word['example_ru'])): ?>
                        <span
                            class="test-tag test-tag-example"
                            title="<?php echo htmlspecialchars($word['example_ru']); ?>"
                            data-tooltip="<?php echo htmlspecialchars($word['example_ru']); ?>"
                        >RU ex.</span>
                    <?php endif; ?>

                    <?php if (!empty($word['example'])): ?>
                        <span
                            class="test-tag test-tag-example"
                            title="<?php echo htmlspecialchars($word['example']); ?>"
                            data-tooltip="<?php echo htmlspecialchars($word['example']); ?>"
                        >EN ex.</span>
                    <?php endif; ?>
                </div>

                <div class="test-stat-divider" aria-hidden="true"></div>

                <div class="test-stat-grid">
                    <div class="test-stat-block">
                        <div class="test-stat-direction">En &rarr; Ru</div>
                        <div class="test-stat-score">
                            <span class="stat-right"><?php echo $right; ?></span>
                            <span class="stat-slash">/</span>
                            <span class="stat-wrong"><?php echo $wrong; ?></span>
                        </div>
                        <div
                            class="test-stat-percent <?php echo $percentClass($enPercent); ?>"
                            style="--percent: <?php echo $enPercent; ?>;"
                        ><?php echo $enPercent; ?>%</div>
                    </div>

                    <div class="test-stat-block">
                        <div class="test-stat-direction">Ru &rarr; En</div>
                        <div class="test-stat-score">
                            <span class="stat-right"><?php echo $rightRu; ?></span>
                            <span class="stat-slash">/</span>
                            <span class="stat-wrong"><?php echo $wrongRu; ?></span>
                        </div>
                        <div
                            class="test-stat-percent <?php echo $percentClass($ruPercent); ?>"
                            style="--percent: <?php echo $ruPercent; ?>;"
                        ><?php echo $ruPercent; ?>%</div>
                    </div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>

</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a href="<?php echo htmlspecialchars($wordsPageUrl(['page' => $currentPage - 1, 'type' => $currentType, 'level' => $currentLevel, 'topic' => $currentTopic])); ?>" class="pagination-btn">&larr; Prev</a>
        <?php else: ?>
            <span class="pagination-btn disabled">&larr; Prev</span>
        <?php endif; ?>

        <div class="pagination-pages">
            <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);

                if ($startPage > 1): ?>
                    <a href="<?php echo htmlspecialchars($wordsPageUrl(['page' => 1, 'type' => $currentType, 'level' => $currentLevel, 'topic' => $currentTopic])); ?>" class="pagination-page">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="pagination-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="pagination-page active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($wordsPageUrl(['page' => $i, 'type' => $currentType, 'level' => $currentLevel, 'topic' => $currentTopic])); ?>" class="pagination-page"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="pagination-dots">...</span>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($wordsPageUrl(['page' => $totalPages, 'type' => $currentType, 'level' => $currentLevel, 'topic' => $currentTopic])); ?>" class="pagination-page"><?php echo $totalPages; ?></a>
                <?php endif; ?>
        </div>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?php echo htmlspecialchars($wordsPageUrl(['page' => $currentPage + 1, 'type' => $currentType, 'level' => $currentLevel, 'topic' => $currentTopic])); ?>" class="pagination-btn">Next &rarr;</a>
        <?php else: ?>
            <span class="pagination-btn disabled">Next &rarr;</span>
        <?php endif; ?>
    </div>

    <div class="pagination-info">
        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?> | Total: <?php echo $totalCount; ?> words
    </div>
<?php endif; ?>

<!-- ===== POPUP: Word Card Settings ===== -->
<div id="word-card-settings-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Memory cards</h3>
            <button class="modal-close" data-close="word-card-settings-modal">&times;</button>
        </div>

        <div class="modal-form word-card-settings-form">
            <div class="word-card-settings-row">
                <div class="form-group word-card-settings-language">
                    <label for="word-card-direction">Language:</label>
                    <select id="word-card-direction" class="test-filter-select">
                        <option value="all">All visible words</option>
                        <option value="translate">English &rarr; Russian</option>
                        <option value="text">Russian &rarr; English</option>
                    </select>
                </div>

                <div class="form-group word-card-settings-count">
                    <label for="word-card-count">Number of words:</label>
                    <input
                        type="number"
                        id="word-card-count"
                        min="1"
                        max="100"
                        value="10"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="word-card-levels">Levels:</label>
                <select id="word-card-levels" class="test-filter-select" multiple>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?php echo htmlspecialchars($level); ?>">
                            <?php echo htmlspecialchars($level); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="word-card-chip-group" data-word-card-chip-select="word-card-levels">
                    <?php foreach ($levels as $level): ?>
                        <button
                            type="button"
                            class="word-card-chip"
                            data-word-card-chip-value="<?php echo htmlspecialchars($level); ?>"
                            aria-pressed="false"
                        >
                            <?php echo htmlspecialchars($level); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="word-card-settings-hint">Можно выбрать несколько значений</div>
            </div>

            <div class="form-group">
                <label for="word-card-topics">Topics:</label>
                <select id="word-card-topics" class="test-filter-select" multiple>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo htmlspecialchars($topic['slug']); ?>">
                            <?php echo htmlspecialchars($topic['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="word-card-settings-hint">Можно выбрать несколько значений</div>
            </div>

            <div class="form-group">
                <label for="word-card-types">Part of speech:</label>
                <select id="word-card-types" class="test-filter-select" multiple>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>">
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="word-card-chip-group" data-word-card-chip-select="word-card-types">
                    <?php foreach ($types as $type): ?>
                        <button
                            type="button"
                            class="word-card-chip"
                            data-word-card-chip-value="<?php echo htmlspecialchars($type); ?>"
                            aria-pressed="false"
                        >
                            <?php echo htmlspecialchars($type); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="word-card-settings-hint">Можно выбрать несколько значений</div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-secondary" data-close="word-card-settings-modal">Cancel</button>
            <button class="btn-primary" id="word-card-start-confirm">Start</button>
        </div>
    </div>
</div>

<!-- ===== POPUP: Word Cards ===== -->
<div id="word-card-modal" class="modal">
    <div class="modal-content word-card-modal-content">
        <div class="modal-header">
            <h3>Words</h3>
            <button class="modal-close" data-close="word-card-modal">&times;</button>
        </div>

        <div class="modal-form word-card-modal-form">
            <div id="word-card-container" class="word-card-container"></div>
        </div>

        <div class="modal-footer word-card-modal-footer">
            <button class="btn-secondary" id="word-card-known-button">Знаю слово</button>
            <button class="btn-secondary" id="word-card-learning-button">Хочу учить</button>
            <button class="btn-primary" id="word-card-next-button">Дальше</button>
        </div>
    </div>
</div>

<?php \App\Core\View::partial('test-settings-modal', [
    'levels' => $levels,
    'types' => $types,
    'topics' => $topics,
]); ?>

<!-- ===== POPUP 2: Test Words ===== -->
<div id="test-words-modal" class="modal">
    <div class="modal-content test-words-content">
        <div class="modal-header">
            <h3>Test</h3>
            <button class="modal-close" data-close="test-words-modal">&times;</button>
        </div>

        <div class="modal-form test-words-form">
            <div id="test-words-container" class="test-words-container">
                <!-- Words will be rendered here -->
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-secondary" data-close="test-words-modal">Cancel</button>
            <button class="btn-primary" id="btn-finish-test">Finish Test</button>
        </div>
    </div>
</div>

<!-- ===== POPUP 3: Test Results ===== -->
<div id="test-results-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Test Results</h3>
            <button class="modal-close" data-close="test-results-modal">&times;</button>
        </div>

        <div class="modal-form">
            <div class="test-results-summary">
                <div class="result-stat">
                    <div class="result-label">Correct:</div>
                    <div class="result-value correct" id="result-correct">0</div>
                </div>
                <div class="result-stat">
                    <div class="result-label">Wrong:</div>
                    <div class="result-value wrong" id="result-wrong">0</div>
                </div>
                <div class="result-stat">
                    <div class="result-label">Percentage:</div>
                    <div class="result-value percentage" id="result-percentage">0%</div>
                </div>
            </div>

            <div id="test-results-details" class="test-results-details">
                <!-- Details will be rendered here -->
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-primary" data-close="test-results-modal">OK</button>
        </div>
    </div>
</div>

<script src="/assets/js/tests.js"></script>
<script src="/assets/js/word-cards.js"></script>
