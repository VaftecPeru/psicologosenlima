<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoInternoProducto extends Model
{
    use HasFactory;

    protected $table = 'pedido_interno_producto';

    protected $fillable = [
        'pedido_interno_id',
        'nombre_producto',
        'cantidad',
        'precio_unitario',
    ];

    // Relación: Un PedidoInternoProducto pertenece a un PedidoInterno
    public function pedidoInterno(): BelongsTo
    {
        return $this->belongsTo(PedidoInterno::class, 'pedido_interno_id');
    }
}