<?php

namespace PaymentGateway\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GatewaySeeder extends Seeder
{
    public function run()
    {
        // Fetch Bank IDs
        $banks = DB::table(config('payment.tables.banks', 'banks'))->pluck('id', 'code');

        $gateways = [
            // PSPs (Direct Bank Gateways)
            [
                'bank_id' => $banks['mellat'] ?? null,
                'name_en' => 'Mellat IPG',
                'name_fa' => 'درگاه پرداخت ملت',
                'driver' => 'mellat',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => $banks['saman'] ?? null,
                'name_en' => 'Saman SEP',
                'name_fa' => 'درگاه پرداخت سامان',
                'driver' => 'saman',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => $banks['melli'] ?? null,
                'name_en' => 'Sadad (Melli)',
                'name_fa' => 'درگاه پرداخت سداد (ملی)',
                'driver' => 'sadad',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => $banks['pasargad'] ?? null,
                'name_en' => 'Pasargad PEP',
                'name_fa' => 'درگاه پرداخت پاسارگاد',
                'driver' => 'pasargad',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => $banks['parsian'] ?? null,
                'name_en' => 'Parsian PEC',
                'name_fa' => 'درگاه پرداخت پارسیان',
                'driver' => 'parsian',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null, // IranKish connects to multiple banks
                'name_en' => 'IranKish',
                'name_fa' => 'ایران کیش',
                'driver' => 'irankish',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null, // AsanPardakht connects to multiple banks
                'name_en' => 'AsanPardakht',
                'name_fa' => 'آسان پرداخت',
                'driver' => 'asanpardakht',
                'is_active' => true,
                'config' => json_encode([]),
            ],

            // Intermediaries (Payment Facilitators)
            [
                'bank_id' => null,
                'name_en' => 'Zarinpal',
                'name_fa' => 'زرین‌پال',
                'driver' => 'zarinpal',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null,
                'name_en' => 'IDPay',
                'name_fa' => 'آیدی پی',
                'driver' => 'idpay',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null,
                'name_en' => 'NextPay',
                'name_fa' => 'نکست پی',
                'driver' => 'nextpay',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null,
                'name_en' => 'PayIr',
                'name_fa' => 'پی دات آی آر',
                'driver' => 'payir',
                'is_active' => true,
                'config' => json_encode([]),
            ],
            [
                'bank_id' => null,
                'name_en' => 'PayPing',
                'name_fa' => 'پی پینگ',
                'driver' => 'payping',
                'is_active' => true,
                'config' => json_encode([]),
            ],
        ];

        DB::table(config('payment.tables.gateways', 'payment_gateways'))->insertOrIgnore($gateways);
    }
}
