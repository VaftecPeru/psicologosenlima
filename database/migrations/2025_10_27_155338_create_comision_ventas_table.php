<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_ventas', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');  // Signed int, para coincidir con usuarios.id
            $table->decimal('comision', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_ventas');
    }
};
