<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuariosTokensTable extends Migration
{
    public function up()
    {
        Schema::create('usuarios_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); 
            $table->string('token', 64); 
            $table->dateTime('expires_at'); 
            $table->foreign('user_id') 
                  ->references('id')
                  ->on('usuarios')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuarios_tokens');
    }
}