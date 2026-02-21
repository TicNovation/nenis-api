<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membresia extends Model
{
    protected $table = 'membresias';

    public $timestamps = false; // Only has created_at

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
