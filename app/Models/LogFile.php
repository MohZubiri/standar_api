<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogFile extends Model
{
    //
    protected $connection = 'mysql1';
    protected $table='api_log_file';
}
