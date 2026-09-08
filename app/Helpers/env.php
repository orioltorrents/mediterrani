<?php

$envData = [];
$envFile = dirname(__DIR__, 2) . '/.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $envData[trim($name)] = trim($value, "\"' ");
        }
    }
}

function env(string $key, $default = null) {
    global $envData;

    if (array_key_exists($key, $envData)) {
        return $envData[$key];
    }

    $value = getenv($key);
    return $value === false ? $default : $value;
}
