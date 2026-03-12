<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Negocio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'negocios';

    protected $fillable = [
        'id_usuario',
        'id_categoria_principal',
        'slug',
        'nombre',
        'descripcion',
        'slogan',
        'palabras_clave',
        'palabras_clave_normalizadas',
        'estatus',
        'estatus_verificacion',
        'alcance_visibilidad',
        'destacado_cache',
        'destacado_titulo_cache',
        'ruta_logo',
        'ruta_imagen_destacada',
        'telefono',
        'whatsapp',
        'correo_contacto',
        'sitio_web',
        'facebook',
        'instagram',
        'tiktok',
        'total_vistas',
        'prioridad_cache',
        'activo',
        'total_items',
        'total_imagenes',
        'total_sucursales',
        'total_ofertas_empleo'

    ];

    protected $casts = [
        'activo' => 'boolean',
        'total_vistas' => 'integer',
        'prioridad_cache' => 'integer',
        'total_items' => 'integer',
        'total_imagenes' => 'integer',
        'total_sucursales' => 'integer',
        'total_ofertas_empleo' => 'integer'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function categoriaPrincipal()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria_principal');
    }

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'negocios_categorias', 'id_negocio', 'id_categoria');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'id_negocio');
    }

    public function sucursales()
    {
        return $this->hasMany(Sucursal::class, 'id_negocio');
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenNegocio::class, 'id_negocio');
    }

    public function empleos()
    {
        return $this->hasMany(OfertaEmpleo::class, 'id_negocio');
    }


    /**
     * SCOPES NUEVOS (REFACCIONADOS)
     * ---------------------------------------------------------
     */

    public function scopeVisibilidadJerarquica($query, $id_estado = null, $id_ciudad = null)
    {
        return $this->scopeVisibilidadJerarquicaOld($query, $id_estado, $id_ciudad);
    }

    public function scopeCercanosA($query, $lat, $lng, $radioKm = 50, $id_estado = null)
    {
        return $this->scopeCercanosAOld($query, $lat, $lng, $radioKm, $id_estado);
    }

    /**
     * SCOPES ANTIGUOS (RESPALDO)
     * ---------------------------------------------------------
     */

    /**
     * Scope para filtrar negocios por jerarquía de visibilidad (pais, estado, ciudad).
     */
    public function scopeVisibilidadJerarquicaOld($query, $id_estado = null, $id_ciudad = null)
    {
        return $query->where(function ($q) use ($id_estado, $id_ciudad) {
            // 1. Nivel País o Público: Siempre visibles
            $q->where('negocios.alcance_visibilidad', 'pais');

            // 2. Nivel Estado: Visible si coincide el estado en alguna sucursal activa
            if ($id_estado) {
                $q->orWhere(function ($sq) use ($id_estado) {
                    $sq->where('negocios.alcance_visibilidad', 'estado')
                       ->whereHas('sucursales', function ($ssq) use ($id_estado) {
                           $ssq->where('id_estado', $id_estado)->where('activo', 1);
                       });
                });
            }

            // 3. Nivel Ciudad: Visible si coincide la ciudad en alguna sucursal activa
            if ($id_ciudad) {
                $q->orWhere(function ($sq) use ($id_ciudad) {
                    $sq->where('negocios.alcance_visibilidad', 'ciudad')
                       ->whereHas('sucursales', function ($ssq) use ($id_ciudad) {
                           $ssq->where('id_ciudad', $id_ciudad)->where('activo', 1);
                       });
                });
            }
        });
    }

    /**
     * Scope optimizado para búsqueda por cercanía (GPS)
     * Utiliza Bounding Box para optimización de índices y Haversine para precisión.
     */
    public function scopeCercanosAOld($query, $lat, $lng, $radioKm = 50, $id_estado = null)
    {
        $lat = (float)$lat;
        $lng = (float)$lng;

        // Bounding Box para optimización de índices
        $deltaLat = $radioKm / 111.1; 
        $deltaLng = $radioKm / (111.1 * cos(deg2rad($lat)));

        // Subconsulta para calcular distancias mínimas por negocio
        // Esto evita el error ONLY_FULL_GROUP_BY al no mezclar negocios.* con GROUP BY
        $subquery = DB::table('sucursales')
            ->select('id_negocio')
            ->selectRaw("MIN(6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distancia", [$lat, $lng, $lat])
            ->where('activo', 1)
            ->where('visibilidad_direccion', 'completa')
            ->whereBetween('lat', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('lng', [$lng - $deltaLng, $lng + $deltaLng]);

        if ($id_estado) {
            $subquery->where('id_estado', $id_estado);
        }

        $subquery->groupBy('id_negocio')
            ->having('distancia', '<=', $radioKm);

        // Unimos la subconsulta a la consulta principal
        return $query->joinSub($subquery, 'localidades', function ($join) {
                $join->on('negocios.id', '=', 'localidades.id_negocio');
            })
            ->select('negocios.*', 'localidades.distancia')
            ->orderBy('localidades.distancia', 'ASC');
    }
}
