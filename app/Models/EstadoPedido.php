<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoPedido extends Model
{
    protected $table = 'estado_pedidos';

    protected $fillable = [
        'shopify_order_id',
        'estado_pago',
        'estado_preparacion',
    ];
}
