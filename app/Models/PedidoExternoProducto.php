<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoExternoProducto extends Model
{
    use HasFactory;

    protected $table = 'pedido_externo_producto';

    protected $fillable = [
        'pedido_externo_id',
        'nombre_producto',
        'cantidad',
        'precio_unitario',
    ];

    // Relación: Un PedidoExternoProducto pertenece a un PedidoExterno
    public function pedidoExterno(): BelongsTo
    {
        return $this->belongsTo(PedidoExterno::class, 'pedido_externo_id');
    }
}