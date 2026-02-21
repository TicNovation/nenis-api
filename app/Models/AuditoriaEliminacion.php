<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaEliminacion extends Model
{
    protected $table = 'auditoria_eliminaciones';

    public $timestamps = false; // Only has created_at in SQL

    protected $fillable = [
        'tipo_objetivo',
        'id_objetivo',
        'accion',
        'motivo',
        'id_usuario_autor',
        'id_admin_autor',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }
}
