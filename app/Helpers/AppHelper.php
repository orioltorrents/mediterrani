<?php

declare(strict_types=1);

class AppHelper
{
    public static function result(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
