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
        'alcance_visibilidad',
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

    /**
     * Scope para filtrar ofertas por jerarquía de visibilidad (pais, estado, ciudad).
     */
    public function scopeVisibilidadJerarquica($query, $id_estado = null, $id_ciudad = null)
    {
        return $query->where(function ($q) use ($id_estado, $id_ciudad) {
            // 1. Nivel País: Siempre visibles
            $q->where('alcance_visibilidad', 'pais');

            // 2. Nivel Estado: Visible si coincide el estado
            if ($id_estado) {
                $q->orWhere(function ($sq) use ($id_estado) {
                    $sq->where('alcance_visibilidad', 'estado')
                       ->where('id_estado', $id_estado);
                });
            }

            // 3. Nivel Ciudad: Visible si coincide la ciudad
            if ($id_ciudad) {
                $q->orWhere(function ($sq) use ($id_ciudad) {
                    $sq->where('alcance_visibilidad', 'ciudad')
                       ->where('id_ciudad', $id_ciudad);
                });
            }
        });
    }
}
