<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_externo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopify_order_id')->unique();
            $table->string('asesor', 100)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('codigo', 100)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('cliente', 150)->nullable();
            $table->string('provincia_distrito', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('referencias', 255)->nullable();
            $table->text('notas_asesor')->nullable();
            $table->text('notas_supervisor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_externo');
    }
};