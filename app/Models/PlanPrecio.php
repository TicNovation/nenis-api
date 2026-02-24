<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPrecio extends Model
{
    protected $table = 'plan_precios';

    protected $fillable = [
        'id_plan',
        'meses',
        'precio',
        'stripe_price_id',
        'etiqueta',
        'ahorro_texto',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'meses' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'id_plan');
    }
}
