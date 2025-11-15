<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoVariante extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'shopify_variant_id',
        'opcion1',
        'opcion2',
        'opcion3',
        'precio',
        'cantidad',
        'sku',
        'url_media',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}