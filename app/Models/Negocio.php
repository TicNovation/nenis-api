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
}
