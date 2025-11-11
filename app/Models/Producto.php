<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $fillable = [
        'id_producto_shopify',
        'titulo',
        'descripcion',
        'multimedia',
        'categoria',
        'precio',
        'cantidad',
        'estado', // ahora es string
    ];

    protected $casts = [
        'multimedia' => 'array',
        'precio' => 'decimal:2',
    ];

    // Scope para buscar por título o código shopify
    public function scopeBuscar($query, $termino)
    {
        if ($termino) {
            return $query->where('titulo', 'LIKE', "%{$termino}%")
                ->orWhere('id_producto_shopify', 'LIKE', "%{$termino}%");
        }
        return $query;
    }
}
