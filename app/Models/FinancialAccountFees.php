<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAccountFees extends Model
{
    //
    
    protected $table     ='financial_accounts_fees';
    protected $primaryKey ='FAF_ID';
    public    $timestamps    = false;
//FAF FinancialAccountFees
    public function FAF()
    {
        return $this->belongsTo('App\Models\financialfees','FEES_ID');

        
    }
}
