<?php

$levels = $levels ?? [];
$types = $types ?? [];
$topics = $topics ?? [];

?>

<!-- ===== POPUP 1: Test Settings ===== -->
<div id="test-settings-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Test Settings</h3>
            <button class="modal-close" data-close="test-settings-modal">&times;</button>
        </div>

        <div class="modal-form test-settings-form">
            <div class="form-group test-settings-direction">
                <label>Direction:</label>
                <div class="radio-group test-settings-radio-group">
                    <label class="radio-label test-settings-radio-card">
                        <input type="radio" name="direction" value="translate" checked>
                        <span>English &rarr; Russian</span>
                    </label>
                    <label class="radio-label test-settings-radio-card">
                        <input type="radio" name="direction" value="text">
                        <span>Russian &rarr; English</span>
                    </label>
                </div>
            </div>

            <div class="form-group test-settings-count">
                <label for="test-count">Number of words:</label>
                <input
                    type="number"
                    id="test-count"
                    name="count"
                    min="1"
                    max="100"
                    value="7"
                >
            </div>

            <div class="form-group">
                <label for="test-levels">Levels:</label>
                <select id="test-levels" name="levels[]" class="test-filter-select" multiple>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?php echo htmlspecialchars($level); ?>">
                            <?php echo htmlspecialchars($level); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="test-chip-group test-chip-group-levels" data-test-chip-select="test-levels">
                    <?php foreach ($levels as $level): ?>
                        <button
                            type="button"
                            class="test-chip test-chip-level"
                            data-test-chip-value="<?php echo htmlspecialchars($level); ?>"
                            aria-pressed="false"
                        >
                            <?php echo htmlspecialchars($level); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="test-topics">Topics:</label>
                <select id="test-topics" name="topics[]" class="test-filter-select" multiple>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo htmlspecialchars($topic['slug']); ?>">
                            <?php echo htmlspecialchars($topic['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="test-types">Part of speech:</label>
                <select id="test-types" name="types[]" class="test-filter-select" multiple>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>">
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="test-chip-group" data-test-chip-select="test-types">
                    <?php foreach ($types as $type): ?>
                        <button
                            type="button"
                            class="test-chip"
                            data-test-chip-value="<?php echo htmlspecialchars($type); ?>"
                            aria-pressed="false"
                        >
                            <?php echo htmlspecialchars($type); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-secondary" data-close="test-settings-modal">Cancel</button>
            <button class="btn-primary" id="btn-start-test-confirm">Start Test</button>
        </div>
    </div>
</div>
