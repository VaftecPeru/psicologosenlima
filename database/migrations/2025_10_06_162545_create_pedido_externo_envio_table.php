<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_externo_envio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopify_order_id')->unique();
            $table->string('estado_agencial', 100)->nullable();
            $table->dateTime('fecha_envio')->nullable();
            $table->dateTime('fecha_llegada')->nullable();
            $table->decimal('costo_envio', 10, 2)->nullable();
            $table->string('codigo_inicial', 100)->nullable();
            $table->decimal('monto_pendiente', 10, 2)->nullable();
            $table->dateTime('fecha_depositada')->nullable();
            $table->string('medio_pago', 100)->nullable();
            $table->string('numero_operacion', 100)->nullable();
            $table->text('notas_administrativas')->nullable();
            $table->timestamps();

            $table->foreign('shopify_order_id')
                  ->references('shopify_order_id')
                  ->on('pedido_externo')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_externo_envio');
    }
};