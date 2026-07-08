<?php

use App\Core\View;

$words = $words ?? [];

?>

<?php View::partial('word-form'); ?>

<?php View::partial('word-table', [
    'words' => $words
]); ?>
