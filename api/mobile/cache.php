<?php

function where2go_mobile_cache_directory(): string
{
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'where2go-mobile-cache';

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    return $directory;
}

function where2go_mobile_cache_key(string $namespace, array $parts = []): string
{
    $safeNamespace = preg_replace('/[^a-z0-9_-]/i', '-', $namespace) ?: 'cache';

    return $safeNamespace . '-' . sha1(json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function where2go_mobile_cache_file(string $key): string
{
    return where2go_mobile_cache_directory() . DIRECTORY_SEPARATOR . $key . '.json';
}

function where2go_mobile_cache_get(string $key, int $ttlSeconds): ?array
{
    $file = where2go_mobile_cache_file($key);

    if ($ttlSeconds <= 0 || !is_file($file)) {
        return null;
    }

    if ((filemtime($file) ?: 0) + $ttlSeconds < time()) {
        @unlink($file);
        return null;
    }

    $payload = json_decode((string) file_get_contents($file), true);

    return is_array($payload) ? $payload : null;
}

function where2go_mobile_cache_set(string $key, array $payload): void
{
    $file = where2go_mobile_cache_file($key);
    $tmpFile = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($encoded === false) {
        return;
    }

    if (file_put_contents($tmpFile, $encoded, LOCK_EX) !== false) {
        @rename($tmpFile, $file);
    } else {
        @unlink($tmpFile);
    }
}

function where2go_mobile_cache_clear_namespace(string $namespace): void
{
    $safeNamespace = preg_replace('/[^a-z0-9_-]/i', '-', $namespace) ?: 'cache';

    foreach (glob(where2go_mobile_cache_directory() . DIRECTORY_SEPARATOR . $safeNamespace . '-*.json') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function where2go_mobile_cache_reply(array $payload, string $status = 'HIT'): void
{
    header('X-Where2Go-Cache: ' . $status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
