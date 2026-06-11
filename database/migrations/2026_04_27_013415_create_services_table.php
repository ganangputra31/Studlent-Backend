<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->increments('id_service');
            $table->unsignedInteger('id_freelancer')->nullable();
            $table->string('judul', 150)->nullable();
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 12, 2)->nullable();
            $table->string('kategori', 100)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_freelancer')
                  ->references('id_user')
                  ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};