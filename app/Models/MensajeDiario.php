<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MensajeDiario extends Model
{
    protected $table = 'mensajes_diarios';

    protected $fillable = [
        'tipo',
        'titulo',
        'contenido',
        'autor',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
