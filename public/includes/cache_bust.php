<?php
$configPath = dirname(__DIR__, 2) . '/config/config.ini';
$cfg = parse_ini_file($configPath, true);
$cacheBust = filter_var($cfg['app']['cache_bust'] ?? true, FILTER_VALIDATE_BOOLEAN);
$cb = $cacheBust ? '?v=' . time() : '';

// Prevent browsers from caching the HTML pages themselves.
// JS/CSS files are safe to cache since their URLs include a timestamp bust.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
