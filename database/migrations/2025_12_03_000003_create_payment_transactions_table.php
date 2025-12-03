<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('payment.tables.transactions', 'payment_transactions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained(config('payment.tables.gateways', 'payment_gateways'))->onDelete('cascade');
            $table->string('order_id')->index();
            $table->unsignedBigInteger('amount');
            $table->string('status')->index(); // pending, success, failed, canceled
            $table->string('ref_id')->nullable()->index();
            $table->string('tracking_code')->nullable()->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('payment.tables.transactions', 'payment_transactions'));
    }
};
