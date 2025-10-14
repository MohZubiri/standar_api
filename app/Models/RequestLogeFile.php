<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLogeFile extends Model
{
    //
    protected $connection = 'mysql2';
    protected $table ='api_request_log_file';
}
