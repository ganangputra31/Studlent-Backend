<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_wallet', function (Blueprint $table) {
            $table->increments('id_wallet');
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_wallet');
    }
};