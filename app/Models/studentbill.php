<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class studentbill extends Model
{
    //
    protected $connection = 'mysql2';
     protected $table='financial_students_bills';
     protected $primaryKey="BILL_ID";
     public $timestamps = false;

   public function invoicedetail()
    {
        return $this->hasMany('App\Models\invoicedetails');
    }

       public function student()
    {
        return $this->belongsTo('App\Models\students','STUDENT_IDENT');
    }
    
    public function fees()
    {
        return $this->belongsTo('App\Models\financialfees','FEES_ID');
    }
    public function feesDetails()
    {
        return $this->belongsTo('App\Models\FinancialFeesDetails','FFF_ID');
    }
}
