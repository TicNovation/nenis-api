<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    protected $table = 'ai_runs';

    // Disabling timestamps since the table uses custom created_at/completed_at/applied_at
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'entidad_tipo',
        'id_entidad',
        'accion',
        'estatus',
        'proveedor',
        'modelo',
        'input_snapshot',
        'output_propuesta',
        'tokens_total',
        'error_mensaje',
        'completed_at',
        'applied_at',
        'created_at'
    ];

    protected $casts = [
        'input_snapshot' => 'json',
        'output_propuesta' => 'json',
        'completed_at' => 'datetime',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function audit()
    {
        return $this->hasOne(AiAudit::class, 'id_ai_run');
    }
}
