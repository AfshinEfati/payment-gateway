<?php

namespace Database\Seeders;

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
            ['name_en' => 'Melli', 'name_fa' => 'ملی', 'code' => 'melli'],
            ['name_en' => 'Tejarat', 'name_fa' => 'تجارت', 'code' => 'tejarat'],
            ['name_en' => 'Saderat', 'name_fa' => 'صادرات', 'code' => 'saderat'],
            ['name_en' => 'Sepah', 'name_fa' => 'سپه', 'code' => 'sepah'],
            ['name_en' => 'Keshavarzi', 'name_fa' => 'کشاورزی', 'code' => 'keshavarzi'],
            ['name_en' => 'Maskan', 'name_fa' => 'مسکن', 'code' => 'maskan'],
            ['name_en' => 'Ayandeh', 'name_fa' => 'آینده', 'code' => 'ayandeh'],
            ['name_en' => 'Eghtesad Novin', 'name_fa' => 'اقتصاد نوین', 'code' => 'enbank'],
            ['name_en' => 'Karafarin', 'name_fa' => 'کارآفرین', 'code' => 'karafarin'],
            ['name_en' => 'Sina', 'name_fa' => 'سینا', 'code' => 'sina'],
            ['name_en' => 'Shahr', 'name_fa' => 'شهر', 'code' => 'shahr'],
            ['name_en' => 'Dey', 'name_fa' => 'دی', 'code' => 'day'],
            ['name_en' => 'Sarmayeh', 'name_fa' => 'سرمایه', 'code' => 'sarmayeh'],
            ['name_en' => 'Iran Zamin', 'name_fa' => 'ایران زمین', 'code' => 'iranzamin'],
            ['name_en' => 'Resalat', 'name_fa' => 'رسالت', 'code' => 'resalat'],
            ['name_en' => 'Refah', 'name_fa' => 'رفاه', 'code' => 'refah'],
        ];

        DB::table(config('payment.tables.banks', 'banks'))->insertOrIgnore($banks);
    }
}
