<?php

$users = $users ?? [];
$quickLinks = $quickLinks ?? [];
$progressColors = ['#a6e85d', '#a78bfa', '#38bdf8', '#f59e0b', '#34d399', '#fb7185'];

?>

<section class="home-dashboard">
    <header class="home-dashboard-header">
        <div>
            <h1>Dashboard</h1>
            <p>Общий прогресс пользователей по тестам и Memory Cards.</p>
        </div>
    </header>

    <section class="home-panel">
        <div class="home-section-heading">
            <h2>Прогресс пользователей</h2>
        </div>

        <div class="home-progress-table-wrap">
            <table class="home-progress-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Прогресс</th>
                        <th>Слова</th>
                        <th>%</th>
                        <th>Точность</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="home-empty-cell">Пока нет пользователей для отображения.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $index => $user): ?>
                        <?php
                        $progressPercent = min(100, max(0, (float)$user['progress_percent']));
                        $barColor = $progressColors[$index % count($progressColors)];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($user['login']) ?></td>
                            <td>
                                <div class="home-progress-bar" aria-label="Progress <?= htmlspecialchars((string)$progressPercent) ?>%">
                                    <span style="width: <?= htmlspecialchars((string)$progressPercent) ?>%; background: <?= htmlspecialchars($barColor) ?>;"></span>
                                </div>
                            </td>
                            <td><?= (int)$user['progress_words'] ?> / <?= (int)$user['total_words'] ?></td>
                            <td><?= htmlspecialchars((string)$user['progress_percent']) ?>%</td>
                            <td><?= htmlspecialchars((string)$user['accuracy_percent']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="home-panel">
        <div class="home-section-heading">
            <h2>Быстрый старт</h2>
        </div>

        <div class="home-quick-grid">
            <?php foreach ($quickLinks as $link): ?>
                <a class="home-quick-link" href="<?= htmlspecialchars($link['href']) ?>">
                    <span class="home-quick-title"><?= htmlspecialchars($link['label']) ?></span>
                    <span class="home-quick-description"><?= htmlspecialchars($link['description'] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</section>
