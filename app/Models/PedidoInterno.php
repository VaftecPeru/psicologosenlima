<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoInterno extends Model
{
    use HasFactory;

    protected $table = 'pedido_interno';

    protected $fillable = [
        'shopify_order_id',
        'asesor',
        'estado',
        'codigo',
        'celular',
        'cliente',
        'provincia_distrito',
        'direccion',
        'referencias',
        'notas_asesor',
        'notas_supervisor',
    ];

    // Relación: Un PedidoInterno tiene muchos PedidoInternoProducto
    public function productos(): HasMany
    {
        return $this->hasMany(PedidoInternoProducto::class, 'pedido_interno_id');
    }
}