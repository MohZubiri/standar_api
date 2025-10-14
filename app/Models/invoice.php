<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class invoice extends Model
{
    //
 //  protected $connection = 'mysql';
 protected $connection = 'mysql2';
     protected $table      ='financial_invoices';
     protected $primaryKey ="INVOICE_IDENT";
     public $timestamps    = false;
}
