<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'verified_at' => 'datetime',
        'amount' => 'integer', // Ensure it's treated as integer
    ];

    public function getTable()
    {
        return config('payment.tables.transactions', 'payment_transactions');
    }

    public function gateway()
    {
        return $this->belongsTo(config('payment.models.gateway', PaymentGateway::class), 'gateway_id');
    }
}
