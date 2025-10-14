<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class API_PYMENT extends Model
{
    //
    protected $connection = 'mysql2';
    protected $table      ='api_payments';
     protected $primaryKey ="API_PAYMENT_ID";
     public $timestamps    = false;
     protected $fillable =[
        'FACULTY_IDENT',
        'PROGRAM_IDENT',
        'STUDENT_IDENT',
        'INVOICE_IDENT',
        'BANK_ID' ,
        'REAL_FEE_AMOUNT',
        'BONDS_ID' ,
        'BONDS_DATE' ,
        'PAYMENT_BY' ,
        'ACTUAL_PAYMENT_DATE' ,
        'PAYMENT_FLAG',
        'UPDATED_BY' ,
        'RECORDED_BY' ,
    ];
}
