<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioSocial extends Model
{
    protected $table = 'usuarios_social';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'proveedor',
        'proveedor_usuario_id',
        'correo_proveedor',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
