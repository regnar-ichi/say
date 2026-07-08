<?php
$content = $content ?? '';
$title = $title ?? 'Vocabulary Trainer';
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/app.css">
    <title><?= htmlspecialchars($title) ?></title>
</head>
<body>

<div class="app-shell">
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="app-main">
        <div class="page-content">
            <?= $content ?>
        </div>
    </main>
</div>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/modal.js"></script>
</body>
</html>
