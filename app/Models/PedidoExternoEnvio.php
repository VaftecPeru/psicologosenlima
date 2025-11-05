<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoExternoEnvio extends Model
{
    use HasFactory;

    protected $table = 'pedido_externo_envio';

    protected $fillable = [
        'shopify_order_id',
        'estado_agencial',
        'fecha_envio',
        'fecha_llegada',
        'costo_envio',
        'codigo_inicial',
        'monto_pendiente',
        'fecha_depositada',
        'medio_pago',
        'numero_operacion',
        'notas_administrativas',
    ];

    // Relación: Un PedidoExternoEnvio pertenece a un PedidoExterno
    public function pedidoExterno(): BelongsTo
    {
        return $this->belongsTo(PedidoExterno::class, 'shopify_order_id', 'shopify_order_id');
    }
}