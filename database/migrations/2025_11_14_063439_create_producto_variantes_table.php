<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->unsignedBigInteger('shopify_variant_id')->unique();
            
            $table->string('opcion1')->nullable();
            $table->string('opcion2')->nullable();
            $table->string('opcion3')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->integer('cantidad')->nullable();
            $table->string('sku')->nullable();
            $table->text('url_media')->nullable();
            
            $table->timestamps();

            $table->index('shopify_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
    }
};