<?php

declare(strict_types=1);

function startAppSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $idleTimeout = 7200;

    ini_set('session.gc_maxlifetime', (string) $idleTimeout);
    ini_set('session.cookie_lifetime', (string) $idleTimeout);

    session_set_cookie_params([
        'lifetime' => $idleTimeout,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleTimeout) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }

    $_SESSION['last_activity'] = time();
}
