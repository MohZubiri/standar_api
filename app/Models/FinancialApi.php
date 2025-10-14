<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialApi extends Model
{
    use HasFactory;
	
	protected $connection = 'mysql2';
    protected $table = "financial_api";
}
