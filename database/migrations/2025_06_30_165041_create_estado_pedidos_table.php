<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_pedidos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_order_id')->unique();
            $table->enum('estado_pago', ['pendiente', 'pagado'])->default('pendiente');
            $table->enum('estado_preparacion', ['no_preparado', 'preparado'])->default('no_preparado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_pedidos');
    }
};
