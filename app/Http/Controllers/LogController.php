<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function index($date)
    {
       $file= 'logs/laravel'.$date.'.log';
        $logFile = storage_path( $file);
        if (File::exists($logFile)) {
            $logs = File::get($logFile);
            return nl2br(e($logs)); // Convert new lines to <br> tags and escape HTML characters
        } else {
            return 'Log file does not exist.';
        }
    }
}
