<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class Membresia extends Model
{
    protected $table = 'membresias';

    //public $timestamps = false; // Only has created_at

    protected $fillable = [
        'id_usuario',
        'id_plan',
        'stripe_pago_id',
        'stripe_cliente_id',
        'meses_comprados',
        'monto_pagado',
        'inicio_en',
        'fin_en',
        'estatus',
        'folio',
    ];

    protected $casts = [
        'inicio_en' => 'datetime',
        'fin_en' => 'datetime',
        'monto_pagado' => 'decimal:2',
        'meses_comprados' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
            
            // Generar un folio único si no tiene uno
            if (!$model->folio) {
                $model->folio = 'NEN-' . strtoupper(Str::random(10));
                
                // Asegurar que sea único (extra safety)
                while (self::where('folio', $model->folio)->exists()) {
                    $model->folio = 'NEN-' . strtoupper(Str::random(10));
                }
            }
        });
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'id_plan');
    }
}
