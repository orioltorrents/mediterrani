<?php

function normalizeBasePath(string $path): string
{
    $path = trim((string) $path, " \t\n\r\0\x0B/");

    if ($path === '' || $path === '.') {
        return '';
    }

    return '/' . str_replace('//', '/', $path);
}

function appBasePath(): string
{
    $baseUrl = env('BASE_URL');

    if ($baseUrl === null || $baseUrl === '') {
        $appUrl = env('APP_URL', '');
        if ($appUrl !== '') {
            $baseUrl = parse_url((string) $appUrl, PHP_URL_PATH) ?: '';
        }
    }

    if ($baseUrl === null || $baseUrl === '') {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
        if ($scriptName !== '') {
            $scriptDir = rtrim(dirname($scriptName), '/');
            if ($scriptDir !== '' && $scriptDir !== '.') {
                $baseUrl = $scriptDir;
            }
        }
    }

    if ($baseUrl === null || $baseUrl === '') {
        return '';
    }

    return normalizeBasePath((string) $baseUrl);
}

function url(string $path = ''): string
{
    $basePath = appBasePath();
    $path = trim($path, '/');

    if ($path === '') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return $basePath . '/' . $path;
}
