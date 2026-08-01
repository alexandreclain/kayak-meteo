<?php

define('PAGE_CACHE_FILE', __DIR__ . '/../cache/page.html');
define('PAGE_CACHE_META_FILE', __DIR__ . '/../cache/page.meta.json');
define('PAGE_CACHE_LOCK_FILE', __DIR__ . '/../cache/page.lock');
define('PAGE_CACHE_TTL', 3600);
define('PAGE_CACHE_LOCK_TTL', 30);

function getPageCache(): ?array
{
    if (!file_exists(PAGE_CACHE_FILE) || !file_exists(PAGE_CACHE_META_FILE)) {
        return null;
    }

    $meta = json_decode(file_get_contents(PAGE_CACHE_META_FILE), true);
    if (!isset($meta['generated_at'])) {
        return null;
    }

    $html = file_get_contents(PAGE_CACHE_FILE);
    if ($html === false || $html === '') {
        return null;
    }

    return [
        'html' => $html,
        'generated_at' => $meta['generated_at'],
        'is_stale' => (time() - $meta['generated_at']) >= PAGE_CACHE_TTL,
    ];
}

function savePageCache(string $html): void
{
    if (!is_dir(dirname(PAGE_CACHE_FILE))) {
        mkdir(dirname(PAGE_CACHE_FILE), 0755, true);
    }
    file_put_contents(PAGE_CACHE_FILE, $html, LOCK_EX);
    file_put_contents(PAGE_CACHE_META_FILE, json_encode(['generated_at' => time()]), LOCK_EX);
}

function acquireRegenerationLock(): bool
{
    if (!is_dir(dirname(PAGE_CACHE_LOCK_FILE))) {
        mkdir(dirname(PAGE_CACHE_LOCK_FILE), 0755, true);
    }

    if (file_exists(PAGE_CACHE_LOCK_FILE) && (time() - filemtime(PAGE_CACHE_LOCK_FILE)) < PAGE_CACHE_LOCK_TTL) {
        return false;
    }

    $handle = fopen(PAGE_CACHE_LOCK_FILE, 'c');
    if (!$handle) {
        return false;
    }

    $acquired = flock($handle, LOCK_EX | LOCK_NB);
    if ($acquired) {
        ftruncate($handle, 0);
        fwrite($handle, (string) time());
        fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);

    return $acquired;
}

function releaseRegenerationLock(): void
{
    if (file_exists(PAGE_CACHE_LOCK_FILE)) {
        unlink(PAGE_CACHE_LOCK_FILE);
    }
}
