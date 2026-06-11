<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->increments('id_room');
            $table->unsignedInteger('id_client')->nullable();
            $table->unsignedInteger('id_freelancer')->nullable();
            $table->unsignedInteger('id_order')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_client')
                  ->references('id_user')
                  ->on('users');

            $table->foreign('id_freelancer')
                  ->references('id_user')
                  ->on('users');

            $table->foreign('id_order')
                  ->references('id_order')
                  ->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};