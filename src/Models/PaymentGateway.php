<?php

namespace PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    public function getTable()
    {
        return config('payment.tables.gateways', 'payment_gateways');
    }

    public function bank()
    {
        return $this->belongsTo(config('payment.models.bank', Bank::class), 'bank_id');
    }

    public function transactions()
    {
        return $this->hasMany(config('payment.models.transaction', PaymentTransaction::class), 'gateway_id');
    }
}
