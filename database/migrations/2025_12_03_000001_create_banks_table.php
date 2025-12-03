<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('payment.tables.banks', 'banks'), function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_fa');
            $table->string('code')->unique();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('payment.tables.banks', 'banks'));
    }
};
