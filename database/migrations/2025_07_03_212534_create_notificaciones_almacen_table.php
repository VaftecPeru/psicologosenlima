<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('notificaciones_almacen', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('shopify_order_id');
        $table->string('mensaje');
        $table->string('tipo')->nullable();
        $table->boolean('leido')->default(false);
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_almacen');
    }
};
