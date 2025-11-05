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
        Schema::create('seguimiento_pedido', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_order_id');
            $table->string('area', 100);
            $table->string('estado', 100);
            $table->integer('responsable_id')->nullable();
            $table->timestamps();

            $table->foreign('responsable_id')
                  ->references('id')
                  ->on('usuarios')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimiento_pedido');
    }
};