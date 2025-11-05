<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoPago extends Model
{
    use HasFactory;

    protected $table = 'seguimiento_pago';

    protected $fillable = [
        'shopify_order_id',
        'monto',
        'metodo',
        'estado',
        'responsable_id',
    ];

    public function responsable()
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }
}