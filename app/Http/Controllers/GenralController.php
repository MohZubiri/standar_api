<?php

namespace App\Http\Controllers;

class GenralController extends Controller
{
    public function showLogs($date)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Invalid date format. Expected YYYY-MM-DD';
        }
        $file = 'logs/lumen' . $date . '.log';
        $projectRoot = dirname(__DIR__, 3);
        $storagePath = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $logFile = $storagePath . DIRECTORY_SEPARATOR . $file;

        if (is_file($logFile)) {
            $logs = @file_get_contents($logFile);
            if ($logs === false) {
                return 'Unable to read log file.';
            }
            return nl2br(htmlspecialchars($logs, ENT_QUOTES, 'UTF-8'));
        }

        return 'Log file does not exist.';
    }
}
