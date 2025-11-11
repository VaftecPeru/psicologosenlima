<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('id_producto_shopify')->nullable()->unique();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->json('multimedia')->nullable();
            $table->string('categoria')->nullable();
            $table->decimal('precio', 10, 2);
            $table->integer('cantidad')->default(0);
            $table->string('estado', 20)->default('activo'); // VARCHAR(20)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
