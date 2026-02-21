<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NegocioCategoria extends Pivot
{
    protected $table = 'negocios_categorias';

    public $timestamps = false;

    protected $fillable = [
        'id_negocio',
        'id_categoria',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }
}
