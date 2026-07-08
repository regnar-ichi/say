<?php

$stats = $stats ?? [];
$problemWords = $problemWords ?? [];
$directions = $stats['directions'] ?? [];
$enRu = $directions['en_ru'] ?? ['right' => 0, 'wrong' => 0, 'accuracy' => 0];
$ruEn = $directions['ru_en'] ?? ['right' => 0, 'wrong' => 0, 'accuracy' => 0];

?>

<section class="account-page">
    <div class="account-panel">
        <div class="account-panel-header">
            <div>
                <h1>Account</h1>
                <p>Личный кабинет и обзор текущего прогресса.</p>
            </div>
            <a class="btn-primary account-logout" href="/logout">Logout</a>
        </div>

        <div class="account-info">
            <div class="account-label">Login</div>
            <div class="account-value"><?php echo htmlspecialchars($login); ?></div>
        </div>
    </div>

    <div class="account-tabs" data-account-tabs>
        <div class="account-tab-list" role="tablist" aria-label="Account sections">
            <button type="button" class="account-tab-button is-active" data-account-tab="overview" role="tab" aria-selected="true">Обзор</button>
            <button type="button" class="account-tab-button" data-account-tab="problem-words" role="tab" aria-selected="false">Проблемные слова</button>
        </div>

        <div class="account-tab-panel is-active" data-account-panel="overview" role="tabpanel">
            <section class="account-panel account-progress-panel">
                <div class="account-section-heading">
                    <h2>Обзор прогресса</h2>
                </div>

                <div class="account-progress-summary">
                    <div>
                        <div class="account-label">Мой прогресс</div>
                        <div class="account-progress-value">
                            <?= (int)($stats['progress_words'] ?? 0) ?> / <?= (int)($stats['total_words'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="account-progress-percent"><?= htmlspecialchars((string)($stats['progress_percent'] ?? 0)) ?>%</div>
                </div>

                <div class="account-progress-bar">
                    <span style="width: <?= htmlspecialchars((string)min(100, max(0, (float)($stats['progress_percent'] ?? 0)))) ?>%;"></span>
                </div>

                <div class="account-stat-grid">
                    <div class="account-stat-card">
                        <div class="account-stat-label">Слова в тестах</div>
                        <div class="account-stat-value"><?= (int)($stats['test_words'] ?? 0) ?></div>
                    </div>
                    <div class="account-stat-card">
                        <div class="account-stat-label">Слова “Знаю”</div>
                        <div class="account-stat-value"><?= (int)($stats['known_words'] ?? 0) ?></div>
                    </div>
                    <div class="account-stat-card">
                        <div class="account-stat-label">Слова “Учу”</div>
                        <div class="account-stat-value"><?= (int)($stats['learning_words'] ?? 0) ?></div>
                    </div>
                    <div class="account-stat-card">
                        <div class="account-stat-label">Просмотрено в карточках</div>
                        <div class="account-stat-value"><?= (int)($stats['card_seen_words'] ?? 0) ?></div>
                    </div>
                    <div class="account-stat-card">
                        <div class="account-stat-label">Точность в тестах</div>
                        <div class="account-stat-value"><?= htmlspecialchars((string)($stats['test_accuracy'] ?? 0)) ?>%</div>
                    </div>
                </div>
            </section>

            <section class="account-panel">
                <div class="account-section-heading">
                    <h2>Направления тестов</h2>
                </div>

                <div class="account-direction-grid">
                    <div class="account-direction-card">
                        <div class="account-direction-title">English → Russian</div>
                        <div class="account-direction-score">
                            <span class="account-right"><?= (int)$enRu['right'] ?></span>
                            <span class="account-divider">/</span>
                            <span class="account-wrong"><?= (int)$enRu['wrong'] ?></span>
                        </div>
                        <div class="account-direction-accuracy"><?= htmlspecialchars((string)$enRu['accuracy']) ?>% accuracy</div>
                    </div>

                    <div class="account-direction-card">
                        <div class="account-direction-title">Russian → English</div>
                        <div class="account-direction-score">
                            <span class="account-right"><?= (int)$ruEn['right'] ?></span>
                            <span class="account-divider">/</span>
                            <span class="account-wrong"><?= (int)$ruEn['wrong'] ?></span>
                        </div>
                        <div class="account-direction-accuracy"><?= htmlspecialchars((string)$ruEn['accuracy']) ?>% accuracy</div>
                    </div>
                </div>
            </section>
        </div>

        <div class="account-tab-panel" data-account-panel="problem-words" role="tabpanel">
            <section class="account-panel">
                <div class="account-section-heading">
                    <h2>Проблемные слова</h2>
                </div>

                <div class="account-problem-table-wrap">
                    <table class="account-problem-table">
                        <thead>
                            <tr>
                                <th>Слово</th>
                                <th>Перевод</th>
                                <th>Ошибки</th>
                                <th>Правильно</th>
                                <th>Точность</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($problemWords)): ?>
                                <tr>
                                    <td colspan="5" class="account-empty-cell">Пока нет слов с ошибками.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($problemWords as $word): ?>
                                <tr>
                                    <td><?= htmlspecialchars($word['text']) ?></td>
                                    <td><?= htmlspecialchars($word['translate']) ?></td>
                                    <td class="account-wrong"><?= (int)$word['errors'] ?></td>
                                    <td class="account-right"><?= (int)$word['correct'] ?></td>
                                    <td><?= htmlspecialchars((string)$word['accuracy']) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>
