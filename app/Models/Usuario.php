<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'correo',
        'telefono',
        'pass',
        'id_plan_activo',
        'total_negocios',
        'total_items',
        'prioridad_cache',
        'max_alcance_visibilidad',
        'activo',
    ];

    protected $hidden = [
        'pass',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'total_negocios' => 'integer',
        'total_items' => 'integer',
        'prioridad_cache' => 'integer',
    ];

    public function getAuthPassword()
    {
        return $this->pass;
    }

    public function planActivo()
    {
        return $this->belongsTo(Plan::class, 'id_plan_activo');
    }

    public function negocios()
    {
        return $this->hasMany(Negocio::class, 'id_usuario');
    }

    public function membresias()
    {
        return $this->hasMany(Membresia::class, 'id_usuario');
    }

    public function redesSociales()
    {
        return $this->hasMany(UsuarioSocial::class, 'id_usuario');
    }

    public function solicitudesArco()
    {
        return $this->hasMany(SolicitudArco::class, 'id_usuario');
    }
}
