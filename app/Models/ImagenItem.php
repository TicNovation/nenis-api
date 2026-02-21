<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenItem extends Model
{
    protected $table = 'imagenes_items';

    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'id_item',
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

    public function item()
    {
        return $this->belongsTo(Item::class, 'id_item');
    }
}
