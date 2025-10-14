<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class students extends Model
{
    //
    protected $connection = 'mysql2';
    protected $table='students';

  public function studentbill()
   
  {
  
    return $this->hasMany('App\Models\studentbill');
    
  }

  public function invoicedetail()
  
  {
  
    return $this->hasMany('App\Models\invoicedetails');
    
  }

}
