<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->increments('id_package');
            $table->unsignedInteger('id_service')->nullable();
            $table->enum('nama', ['basic', 'standard', 'premium'])->nullable();
            $table->decimal('harga', 12, 2)->nullable();
            $table->integer('delivery_time')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_service')
                  ->references('id_service')
                  ->on('services')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};