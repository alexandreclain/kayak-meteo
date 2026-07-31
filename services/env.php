<?php
// services/env.php

/**
 * Loads key=value pairs from a .env file into getenv()/$_ENV.
 * Lines starting with # are ignored. Silently does nothing if the file is absent.
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}
