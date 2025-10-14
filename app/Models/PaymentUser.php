<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentUser extends Model
{
    //
    protected $connection = 'mysql2';
    protected $table      ='api_payment_user';
     protected $primaryKey ='PAYMENT_USER_IDENT';
     public    $timestamps    = false;
     protected $guarded = [];
}
