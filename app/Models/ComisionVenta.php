<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComisionVenta extends Model
{
    use HasFactory;

    protected $table = 'comision_ventas';

    protected $fillable = [
        'user_id',
        'comision',
    ];
}