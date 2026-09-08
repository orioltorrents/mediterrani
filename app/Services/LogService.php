<?php

class LogService
{
    public function write(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
