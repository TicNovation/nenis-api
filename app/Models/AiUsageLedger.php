<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLedger extends Model
{
    protected $table = 'ai_usage_ledger';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'mes_anio',
        'id_ai_run',
        'accion',
        'tokens_entrada',
        'tokens_salida',
        'tokens_total',
        'costo_usd',
        'created_at'
    ];

    protected $casts = [
        'costo_usd' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function run()
    {
        return $this->belongsTo(AiRun::class, 'id_ai_run');
    }
}
