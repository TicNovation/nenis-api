<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'activo' => 'boolean',
        'total_vistas' => 'integer',
        'prioridad_cache' => 'integer',
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
     * Scope para filtrar negocios por jerarquía de visibilidad (pais, estado, ciudad).
     */
    public function scopeVisibilidadJerarquica($query, $id_estado = null, $id_ciudad = null)
    {
        return $query->where(function ($q) use ($id_estado, $id_ciudad) {
            // 1. Nivel País o Público: Siempre visibles
            $q->where('alcance_visibilidad', 'pais');

            // 2. Nivel Estado: Visible si coincide el estado en alguna sucursal activa
            if ($id_estado) {
                $q->orWhere(function ($sq) use ($id_estado) {
                    $sq->where('alcance_visibilidad', 'estado')
                       ->whereHas('sucursales', function ($ssq) use ($id_estado) {
                           $ssq->where('id_estado', $id_estado)->where('activo', 1);
                       });
                });
            }

            // 3. Nivel Ciudad: Visible si coincide la ciudad en alguna sucursal activa
            if ($id_ciudad) {
                $q->orWhere(function ($sq) use ($id_ciudad) {
                    $sq->where('alcance_visibilidad', 'ciudad')
                       ->whereHas('sucursales', function ($ssq) use ($id_ciudad) {
                           $ssq->where('id_ciudad', $id_ciudad)->where('activo', 1);
                       });
                });
            }
        });
    }
}
