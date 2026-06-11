<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger', function (Blueprint $table) {
            $table->increments('id_ledger');
            $table->unsignedInteger('id_user')->nullable();
            $table->string('type', 20)->nullable(); // credit / debit
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('source', 50)->nullable();
            $table->integer('reference_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); 

            $table->foreign('id_user')
                  ->references('id_user')
                  ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger');
    }
};