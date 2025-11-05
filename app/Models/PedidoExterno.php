<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PedidoExterno extends Model
{
    use HasFactory;

    protected $table = 'pedido_externo';

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

    // Relación: Un PedidoExterno tiene muchos PedidoExternoProducto
    public function productos(): HasMany
    {
        return $this->hasMany(PedidoExternoProducto::class, 'pedido_externo_id');
    }

    // Relación: Un PedidoExterno tiene uno PedidoExternoEnvio
    public function envio(): HasOne
    {
        return $this->hasOne(PedidoExternoEnvio::class, 'shopify_order_id', 'shopify_order_id');
    }
}