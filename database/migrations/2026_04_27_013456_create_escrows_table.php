<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow', function (Blueprint $table) {
            $table->increments('id_escrow');
            $table->unsignedInteger('id_payment')->unique()->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('platform_fee', 12, 2)->nullable();
            $table->decimal('freelancer_amount', 12, 2)->nullable();
            $table->enum('status', ['hold', 'released', 'refunded'])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_payment')
                  ->references('id_payment')
                  ->on('payments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow');
    }
};