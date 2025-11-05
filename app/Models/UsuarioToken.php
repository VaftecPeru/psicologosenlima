<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioToken extends Model
{
    protected $table = 'usuarios_tokens';

    protected $fillable = ['user_id', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // Desactivar marcas temporales
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }
}