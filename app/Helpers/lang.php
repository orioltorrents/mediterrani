<?php

declare(strict_types=1);

function supportedLanguages(): array
{
    return ['ca', 'es', 'en', 'fr'];
}

function setLanguage(string $lang, bool $fromRoute = false): string
{
    startAppSession();

    if (!in_array($lang, supportedLanguages(), true)) {
        $lang = 'ca';
    }

    $_SESSION['lang'] = $lang;

    if ($fromRoute) {
        $GLOBALS['app_route_language'] = $lang;
    }

    return $lang;
}

function getLanguage(): string
{
    startAppSession();

    if (isset($GLOBALS['app_route_language']) && in_array($GLOBALS['app_route_language'], supportedLanguages(), true)) {
        return (string) $GLOBALS['app_route_language'];
    }

    if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) {
        return setLanguage((string) $_GET['lang']);
    }

    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], supportedLanguages(), true)) {
        return $_SESSION['lang'];
    }

    return setLanguage('ca');
}

function trans(string $key, ?string $lang = null): string
{
    $lang = $lang ?? getLanguage();
    $file = dirname(__DIR__, 2) . '/resources/lang/' . $lang . '.php';

    if (!is_file($file)) {
        $file = dirname(__DIR__, 2) . '/resources/lang/ca.php';
    }

    $translations = include $file;
    return $translations[$key] ?? $key;
}
