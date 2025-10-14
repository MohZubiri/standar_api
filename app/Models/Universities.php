<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Universities extends Model
{
    //
    protected $connection = 'mysql';
    protected $table='universities';
    protected $primaryKey="UNID";
    public $timestamps = false;
}