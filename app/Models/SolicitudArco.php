<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudArco extends Model
{
    protected $table = 'solicitudes_arco';

    protected $fillable = [
        'id_usuario',
        'tipo',
        'estatus',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
