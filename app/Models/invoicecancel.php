<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class invoicecancel extends Model
{
    //
    protected $connection = 'mysql2';
     protected $table      ='financial_cancel_invoices';
     protected $primaryKey ='IDENT';
     public    $timestamps    = false;
     protected $guarded = [];

    

}
