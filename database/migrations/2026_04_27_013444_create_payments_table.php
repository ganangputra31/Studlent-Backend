<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id_payment');
            $table->unsignedInteger('id_order')->unique()->nullable();
            $table->string('metode', 50)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('gateway_trx_id', 100)->nullable();
            $table->text('payment_url')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->nullable();
            $table->enum('escrow_status', ['hold', 'released', 'refunded'])->nullable();
            $table->decimal('fee_percent', 5, 2)->nullable();
            $table->decimal('platform_fee', 12, 2)->nullable();
            $table->decimal('freelancer_receive', 12, 2)->nullable();
            $table->timestamp('tanggal_bayar')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_order')
                  ->references('id_order')
                  ->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};