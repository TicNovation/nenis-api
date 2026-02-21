<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SucursalHorario extends Model
{
    protected $table = 'sucursales_horarios';

    protected $fillable = [
        'id_sucursal',
        'dia_semana',
        'hora_apertura',
        'hora_cierre',
        'es_cerrado',
    ];

    protected $casts = [
        'es_cerrado' => 'boolean',
        'dia_semana' => 'integer',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }
}
