<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'stripe_precio_id',
        'precio_mensual',
        'max_negocios',
        'max_items',
        'max_ofertas_empleo_activas',
        'max_imagenes_item',
        'max_imagenes_negocio',
        'max_alcance_visibilidad',
        'max_ia_consultas',
        'incluye_ia_negocios',
        'permite_links_items',
        'incluye_analytics',
        'incluye_google_places',
        'prioridad_busqueda',
        'destacado',
        'activo',
    ];

    protected $casts = [
        'precio_mensual' => 'decimal:2',
        'max_items' => 'integer',
        'max_ofertas_empleo_activas' => 'integer',
        'max_imagenes_item' => 'integer',
        'max_imagenes_negocio' => 'integer',
        'max_ia_consultas' => 'integer',
        'incluye_ia_negocios' => 'boolean',
        'permite_links_items' => 'boolean',
        'incluye_analytics' => 'boolean',
        'incluye_google_places' => 'boolean',
        'prioridad_busqueda' => 'integer',
        'destacado' => 'boolean',
        'activo' => 'boolean',
    ];

    public function precios()
    {
        return $this->hasMany(PlanPrecio::class, 'id_plan');
    }
}
