<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenNegocio extends Model
{
    protected $table = 'imagenes_negocios';

    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'id_negocio',
        'ruta',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function negocio()
    {
        return $this->belongsTo(Negocio::class, 'id_negocio');
    }
}
