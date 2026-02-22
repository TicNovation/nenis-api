<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'id_negocio',
        'id_estado',
        'id_ciudad',
        'direccion_texto',
        'visibilidad_direccion', // 'estado', 'ciudad','completa'
        'codigo_postal',
        'es_principal',
        'lat',
        'lng',
        'google_place_id',
        'activo',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'activo' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class, 'id_negocio');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'id_ciudad');
    }

    public function horarios()
    {
        return $this->hasMany(SucursalHorario::class, 'id_sucursal');
    }
}
