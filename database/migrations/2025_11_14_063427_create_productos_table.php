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
            $table->unsignedBigInteger('shopify_product_id')->unique();
            $table->string('titulo');
            $table->longText('descripcion')->nullable();
            $table->string('tipo_producto')->nullable();
            $table->text('tags')->nullable();
            $table->string('estado')->nullable();
            $table->unsignedBigInteger('locacion_id')->nullable();
            $table->text('url_media')->nullable();
            $table->timestamps();

            $table->index('shopify_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};