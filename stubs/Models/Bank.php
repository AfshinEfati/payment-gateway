<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $guarded = ['id'];

    public function getTable()
    {
        return config('payment.tables.banks', 'banks');
    }

    public function gateways()
    {
        return $this->hasMany(config('payment.models.gateway', PaymentGateway::class), 'bank_id');
    }
}
