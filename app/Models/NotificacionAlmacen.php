<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificacionAlmacen extends Model
{
    protected $table = 'notificaciones_almacen';

    protected $fillable = [
        'shopify_order_id',
        'mensaje',
        'tipo',
        'leido',
    ];
}