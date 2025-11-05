<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoPedido extends Model
{
    use HasFactory;

    protected $table = 'seguimiento_pedido';

    protected $fillable = [
        'shopify_order_id',
        'area',
        'estado',
        'responsable_id',
    ];

    public function responsable()
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }
}