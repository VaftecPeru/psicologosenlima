<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_pago', function (Blueprint $table) {
            $table->id(); // BIGINT(20) UNSIGNED AUTO_INCREMENT con PRIMARY KEY
            $table->unsignedBigInteger('shopify_order_id');
            $table->decimal('monto', 10, 2);
            $table->string('metodo', 100);
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

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_pago');
    }
};