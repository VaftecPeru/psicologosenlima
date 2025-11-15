<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopify_product_id',
        'titulo',
        'descripcion',
        'tipo_producto',
        'tags',
        'estado',
        'locacion_id',
        'url_media',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function variantes()
    {
        return $this->hasMany(ProductoVariante::class, 'producto_id');
    }

    // Scope para búsqueda
    public function scopeBuscar($query, $termino)
    {
        return $query->where('titulo', 'LIKE', "%{$termino}%")
                     ->orWhere('sku', 'LIKE', "%{$termino}%")
                     ->orWhere('tags', 'LIKE', "%{$termino}%");
    }
}