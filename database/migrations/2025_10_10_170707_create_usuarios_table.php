<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id()->startingValue(10); 
            $table->string('nombre_completo', 100);
            $table->string('correo', 100)->unique();
            $table->string('contraseña', 255);
            $table->unsignedBigInteger('rol_id');
            $table->boolean('estado')->default(0); // Cambiado a 0 (no en línea)
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('rol_id')
                  ->references('id')
                  ->on('roles')
                  ->onUpdate('cascade');

            // Configurar charset y collation
            $table->charset = 'latin1';
            $table->collation = 'latin1_swedish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};