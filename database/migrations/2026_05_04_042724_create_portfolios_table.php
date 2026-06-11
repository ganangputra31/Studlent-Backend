<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->increments('id_portfolio');
            $table->unsignedInteger('id_user')->nullable();
            $table->string('judul', 150)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('file_url', 255)->nullable();
            $table->string('thumbnail_url', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_user')
                  ->references('id_user')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};