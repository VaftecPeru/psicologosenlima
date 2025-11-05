<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_interno_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_interno_id')
                  ->constrained('pedido_interno')
                  ->onDelete('cascade');
            $table->string('nombre_producto', 255);
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_interno_producto');
    }
};
