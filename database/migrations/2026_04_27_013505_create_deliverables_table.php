<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->increments('id_file');
            $table->unsignedInteger('id_order')->nullable();
            $table->string('file_hasil', 255)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->foreign('id_order')
                  ->references('id_order')
                  ->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};