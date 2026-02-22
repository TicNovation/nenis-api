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
        'alcance_nivel',//pais, estado, ciudad
        'id_estado',
        'id_ciudad',
        'prioridad',//1,2,3,4,5
        'estatus_cotizacion',//'borrador','aceptado','rechazado'
        'inicia_en',
        'termina_en',
        'activo',
    ];

    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'activo' => 'boolean',
    ];

    /**
     * Scope para filtrar banners por alcance jerárquico.
     */
    public function scopeAlcance($query, $id_estado = null, $id_ciudad = null)
    {
        return $query->where('activo', 1)
            ->where('estatus_cotizacion', 'aceptado')
            ->where('inicia_en', '<=', now())
            ->where('termina_en', '>=', now())
            ->where(function ($q) use ($id_estado, $id_ciudad) {
                $q->where('alcance_nivel', 'pais');

                if ($id_estado) {
                    $q->orWhere(function ($sq) use ($id_estado) {
                        $sq->where('alcance_nivel', 'estado')
                            ->where('id_estado', $id_estado);
                    });
                }

                if ($id_ciudad) {
                    $q->orWhere(function ($sq) use ($id_ciudad) {
                        $sq->where('alcance_nivel', 'ciudad')
                            ->where('id_ciudad', $id_ciudad);
                    });
                }
            })
            ->orderBy('prioridad', 'DESC');
    }
}
