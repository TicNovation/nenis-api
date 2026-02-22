<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfertaEmpleo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ofertas_empleo';

    protected $fillable = [
        'id_negocio',
        'id_admin_publicador',
        'publicador_tipo',
        'titulo',
        'descripcion',
        'requisitos',
        'beneficios',
        'correo_contacto',
        'telefono_contacto',
        'organizacion_externa',
        'id_estado',
        'id_ciudad',
        'es_remoto',
        'estatus',
        'expira_en',
        'activo'
    ];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class, 'id_negocio');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin_publicador');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'id_ciudad');
    }
}
