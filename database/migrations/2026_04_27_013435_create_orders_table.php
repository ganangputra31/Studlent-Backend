<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id_order');
            $table->unsignedInteger('id_client')->nullable();
            $table->unsignedInteger('id_service')->nullable();
            $table->text('detail_pesanan')->nullable();
            $table->date('deadline')->nullable();
            $table->enum('status', [
                'menunggu_pembayaran',
                'paid',
                'diproses',
                'hasil_dikirim',
                'revisi',
                'selesai',
                'dibatalkan'
            ])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_client')
                  ->references('id_user')
                  ->on('users');

            $table->foreign('id_service')
                  ->references('id_service')
                  ->on('services');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};