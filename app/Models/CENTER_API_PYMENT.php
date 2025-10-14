<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CENTER_API_PYMENT extends Model
{
    protected $connection = 'center';
    protected $table      ='api_payments';
    
    protected $fillable =[ 'student_id', 'invoice_ident', 'bank_id', 'amount', 'bound_id', 'bound_date', 'payment_by','actual_payment_date', 'payment_flag'];
 
}
