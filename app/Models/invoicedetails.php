<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class invoicedetails extends Model
{
    //
    protected $connection = 'mysql2';
      protected $table ='financial_invoices_details';
       protected $primaryKey ='ID_IDENT';
    public    $timestamps    = false;

       public function studentbill()
    {
        return $this->belongsTo('App\Models\studentbill','BILL_ID');
    }

        public function student()
    {
        return $this->belongsTo('App\Models\students','STUDENT_IDENT');
    }
     	 
}
