<?php

$word = $word ?? null;

?>

<h2>Edit word</h2>

<?php if ($word): ?>

    <form method="post" action="/ajax.php">
        <input type="hidden" name="action" value="word_update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($word['id']); ?>">

        <div>
            <input type="text" name="text" value="<?php echo htmlspecialchars($word['text']); ?>">
        </div>

        <div>
            <input type="text" name="translate" value="<?php echo htmlspecialchars($word['translate']); ?>">
        </div>

        <button type="submit">Save</button>
    </form>

<?php else: ?>

    <p>Word not found.</p>

<?php endif; ?>