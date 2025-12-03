<?php

namespace PaymentGateway\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    public function run()
    {
        $banks = [
            ['name_en' => 'Mellat', 'name_fa' => 'ملت', 'code' => 'mellat'],
            ['name_en' => 'Saman', 'name_fa' => 'سامان', 'code' => 'saman'],
            ['name_en' => 'Pasargad', 'name_fa' => 'پاسارگاد', 'code' => 'pasargad'],
            ['name_en' => 'Parsian', 'name_fa' => 'پارسیان', 'code' => 'parsian'],
            ['name_en' => 'Ayandeh', 'name_fa' => 'آینده', 'code' => 'ayandeh'],
            ['name_en' => 'Eghtesad Novin', 'name_fa' => 'اقتصاد نوین', 'code' => 'enbank'],
            ['name_en' => 'Melli', 'name_fa' => 'ملی', 'code' => 'melli'],
        ];

        DB::table(config('payment.tables.banks', 'banks'))->insertOrIgnore($banks);
    }
}
