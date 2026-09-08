<?php

function view(string $view, array $data = []): string {
    extract($data);

    $viewFile = dirname(__DIR__, 2) . '/resources/views/' . str_replace('.', '/', $view) . '.php';

    if (!is_file($viewFile)) {
        throw new RuntimeException("Vista no trobada: {$view}");
    }

    ob_start();
    include $viewFile;
    return ob_get_clean();
}
