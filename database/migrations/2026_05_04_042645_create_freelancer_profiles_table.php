<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->increments('id_profile');
            $table->unsignedInteger('id_user')->unique()->nullable();
            $table->string('professional_status', 100)->nullable();
            $table->string('universitas', 150)->nullable();
            $table->string('jurusan', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('no_rekening', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('total_rating')->default(0);
            $table->integer('total_order')->default(0);
            $table->boolean('is_verified')->default(false);
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
        Schema::dropIfExists('freelancer_profiles');
    }
};