<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->increments('id_chat');
            $table->unsignedInteger('id_order')->nullable();
            $table->unsignedInteger('sender_id')->nullable();
            $table->text('pesan')->nullable();
            $table->timestamp('waktu')->useCurrent();
            $table->timestamp('updated_at')->nullable(); 

            $table->foreign('id_order')
                  ->references('id_order')
                  ->on('orders');

            $table->foreign('sender_id')
                  ->references('id_user')
                  ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};