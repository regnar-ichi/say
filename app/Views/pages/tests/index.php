<?php

use App\Core\View;

$levels = $levels ?? [];
$types = $types ?? [];
$topics = $topics ?? [];

?>

<section class="test-page-shell">
    <div class="test-page-panel">
        <header class="test-page-header">
            <div>
                <h1>Tests</h1>
                <p>Choose a direction and filters, then complete the test in a full-page workspace.</p>
            </div>
            <button type="button" class="btn-primary test-page-start" id="btn-start-test">Start Test</button>
        </header>

        <div class="test-page-session-notice" id="test-page-session-notice" hidden>
            <div class="test-page-session-copy">
                <strong id="test-page-session-title">Saved test restored</strong>
                <p id="test-page-session-text">You can continue the saved test or start again.</p>
            </div>
            <div class="test-page-session-actions">
                <button type="button" class="test-page-secondary-btn" id="btn-test-session-continue">Continue</button>
                <button type="button" class="test-page-secondary-btn" id="btn-test-session-new">Start new test</button>
                <button type="button" class="test-page-ghost-btn" id="btn-test-session-clear">Clear saved test</button>
            </div>
        </div>

        <div class="test-page-empty" id="test-page-empty">
            <h2>Ready when you are</h2>
            <p>Start a test to practice selected words with a comfortable page layout and regular page scrolling.</p>
        </div>

        <section class="test-page-workspace" id="test-page-workspace" hidden>
            <div class="test-page-workspace-header">
                <div>
                    <h2>Current Test</h2>
                    <p id="test-page-meta">Answer the prompts below, then finish the test.</p>
                </div>
                <button type="button" class="btn-primary" id="btn-finish-test">Finish Test</button>
            </div>

            <div id="test-page-words-container" class="test-page-words-container"></div>
        </section>

        <section class="test-page-results" id="test-page-results" hidden>
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

            <div id="test-results-details" class="test-results-details"></div>
        </section>
    </div>
</section>

<?php View::partial('test-settings-modal', [
    'levels' => $levels,
    'types' => $types,
    'topics' => $topics,
]); ?>

<script src="/assets/js/tests.js"></script>
