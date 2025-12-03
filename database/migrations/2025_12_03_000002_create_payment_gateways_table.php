<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('payment.tables.gateways', 'payment_gateways'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained(config('payment.tables.banks', 'banks'))->onDelete('cascade');
            $table->string('name_en');
            $table->string('name_fa');
            $table->string('driver');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('payment.tables.gateways', 'payment_gateways'));
    }
};
