<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->increments('id_revisi');
            $table->unsignedInteger('id_order')->nullable();
            $table->text('pesan')->nullable();
            $table->enum('status', ['pending', 'dikerjakan', 'selesai'])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_order')
                  ->references('id_order')
                  ->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};