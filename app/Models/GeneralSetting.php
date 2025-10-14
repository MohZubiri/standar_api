<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    //
    //   protected $connection = 'mysql2';
    protected $table ='financial_settings';

    public $timestamps    = false;
	
	
	
	 public function scopeApi($query)
    {
        return $query->where('SETTING_GROUP', 'API')->where('SETTING_KEY', 'PAYMENT_INVOICE_PREFIX')->where('IS_ENABLE', '1');
    }
}