<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelancer_wallet', function (Blueprint $table) {
            $table->increments('id_wallet');
            $table->unsignedInteger('id_user')->unique()->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable(); 

            $table->foreign('id_user')
                  ->references('id_user')
                  ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_wallet');
    }
};