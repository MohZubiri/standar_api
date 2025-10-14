<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingPayment extends Model
{
    use HasFactory;

    protected $table = 'api_pending_payments';

    protected $fillable = [
        'api_payment_id',
        'invoice_id',
        'payment_key',
        'status_flag',
    ];

    /**
     * Relation with Invoice (if you have an invoices table/model).
     */
    public function invoice()
    {
        return $this->belongsTo(invoice::class, 'invoice_id');
    }

    /**
     * Relation with API Payment
     */
    public function apiPayment()
    {
        return $this->belongsTo(API_PYMENT::class, 'api_payment_id');
    }
}
