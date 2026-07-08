<?php

use App\Core\View;

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

?>

<?php View::partial('word-form'); ?>

<?php View::partial('word-table', [
    'words' => $words,
    'currentType' => $currentType,
    'currentLevel' => $currentLevel,
    'currentTopic' => $currentTopic,
    'currentPage' => $currentPage,
    'totalPages' => $totalPages,
    'totalCount' => $totalCount,
    'limit' => $limit,
    'levels' => $levels,
    'types' => $types,
    'topics' => $topics
]); ?>
