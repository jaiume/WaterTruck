<?php
$configPath = dirname(__DIR__, 2) . '/config/config.ini';
$cfg = parse_ini_file($configPath, true);
$cacheBust = filter_var($cfg['app']['cache_bust'] ?? true, FILTER_VALIDATE_BOOLEAN);
$cb = $cacheBust ? '?v=' . ($cfg['app']['asset_version'] ?? time()) : '';
