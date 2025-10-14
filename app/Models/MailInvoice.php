<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailInvoice extends Model
{
  
    protected $table = "mail_invoice";

     // Define the primary key (if not 'id')
     protected $primaryKey = "INVOICE_IDENT";

      // Allow mass assignment for these fields
    protected $fillable = [
                    'INVOICE_IDENT',
                    'PAYMENT',
                    'BOUND_ID',
                    'BOUND_DATE'
    ];

    
}