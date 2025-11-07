<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre_completo',
        'correo',
        'contraseña',
        'rol_id',
        'estado',
    ];

    protected $hidden = [
        'contraseña',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->contraseña;
    }

    public function tokens()
    {
        return $this->hasMany(UsuarioToken::class, 'user_id');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}
