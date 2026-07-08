<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$isActive = static function (array $paths) use ($path): string {
    return in_array($path, $paths, true) ? ' class="active"' : '';
};

?>

<header>
    <aside class="app-sidebar">
        <div class="brand">
            <div class="brand-title test test2">foxSay test5</div><!-- test -->
            <div class="brand-subtitle">your language den</div>
        </div>

        <nav class="side-nav">
            <a href="/"<?php echo $isActive(['/']); ?>>HOME</a>
            <a href="/words"<?php echo $isActive(['/words']); ?>>WORDS</a>
            <a href="/tests"<?php echo $isActive(['/tests']); ?>>TESTS</a>
            <a href="/reader"<?php echo $isActive(['/reader']); ?>>Reader</a>
            <a href="/library"<?php echo $isActive(['/library']); ?>>Library</a>
            <a href="/grammar-rules"<?php echo $isActive(['/grammar-rules']); ?>>Grammar Rules</a>
            <a href="/settings"<?php echo $isActive(['/settings']); ?>>Settings</a>
            <a href="/account"<?php echo $isActive(['/account']); ?>>Account</a>
        </nav>

        <div class="sidebar-fox">
            <img src="/assets/images/fox-sidebar.png" alt="Fox">
        </div>
    </aside>
</header>
