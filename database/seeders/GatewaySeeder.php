<?php

namespace PaymentGateway\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GatewaySeeder extends Seeder
{
    public function run()
    {
        // Ensure banks exist first
        $mellatId = DB::table(config('payment.tables.banks', 'banks'))->where('code', 'mellat')->value('id');
        $samanId = DB::table(config('payment.tables.banks', 'banks'))->where('code', 'saman')->value('id');

        $gateways = [
            [
                'bank_id' => $mellatId,
                'name_en' => 'Mellat IPG',
                'name_fa' => 'درگاه پرداخت ملت',
                'driver' => 'mellat',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => $samanId,
                'name_en' => 'Saman SEP',
                'name_fa' => 'درگاه پرداخت سامان',
                'driver' => 'saman',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null, // Zarinpal is not a bank
                'name_en' => 'Zarinpal',
                'name_fa' => 'زرین‌پال',
                'driver' => 'zarinpal',
                'is_active' => true,
                'config' => json_encode([]),
            ],
        ];

        DB::table(config('payment.tables.gateways', 'payment_gateways'))->insertOrIgnore($gateways);
    }
}
