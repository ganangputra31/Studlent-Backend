<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->increments('id_withdraw');
            $table->unsignedInteger('id_user')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('no_rekening', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); 

            $table->foreign('id_user')
                  ->references('id_user')
                  ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};