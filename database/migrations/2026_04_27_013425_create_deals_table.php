<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->increments('id_deal');
            $table->unsignedInteger('id_client')->nullable();
            $table->unsignedInteger('id_freelancer')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_client')
                  ->references('id_user')
                  ->on('users');

            $table->foreign('id_freelancer')
                  ->references('id_user')
                  ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};