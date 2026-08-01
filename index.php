<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/services/pagecache.php';

function renderPage(): string
{
    ob_start();
    require __DIR__ . '/view.php';
    return ob_get_clean();
}

$cached = getPageCache();

if ($cached !== null && !$cached['is_stale']) {
    echo $cached['html'];
    exit;
}

if ($cached !== null && $cached['is_stale']) {
    echo $cached['html'];
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    if (acquireRegenerationLock()) {
        savePageCache(renderPage());
        releaseRegenerationLock();
    }
    exit;
}

if (acquireRegenerationLock()) {
    $html = renderPage();
    savePageCache($html);
    releaseRegenerationLock();
    echo $html;
} else {
    usleep(300000);
    $fresh = getPageCache();
    echo $fresh !== null ? $fresh['html'] : renderPage();
}
