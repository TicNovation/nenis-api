<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'id_padre',
        'nombre',
        'ruta_imagen_destacada',
        'slug',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function padre()
    {
        return $this->belongsTo(Categoria::class, 'id_padre');
    }

    public function hijos()
    {
        return $this->hasMany(Categoria::class, 'id_padre');
    }
}
