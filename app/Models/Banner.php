<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';

    protected $fillable = [
        'id_anunciante',
        'ruta_imagen',
        'enlace_externo',
        'alcance_nivel',
        'id_estado',
        'id_ciudad',
        'prioridad',
        'estatus_cotizacion',
        'inicia_en',
        'termina_en',
        'activo',
    ];

    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'activo' => 'boolean',
    ];
}
