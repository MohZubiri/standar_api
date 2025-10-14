<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialFeesDetails extends Model
{
    //
    protected $connection = 'mysql1';
    protected $table     ='financial_fees_details';
    protected $primaryKey ='FFF_ID';
    public    $timestamps    = false;

    public function studentbill()
    {
        return $this->hasMany('App\Models\studentbill');

        
    }

   
}
