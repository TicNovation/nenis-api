<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'items';

    protected $fillable = [
        'id_negocio',
        'id_categoria',
        'slug',
        'nombre',
        'descripcion',
        'ruta_imagen_destacada',
        'tipo_item',
        'precio',
        'url_externa',
        'total_vistas',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
        'total_vistas' => 'integer',
    ];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class, 'id_negocio');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenItem::class, 'id_item');
    }
}
