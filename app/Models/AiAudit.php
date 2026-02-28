<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAudit extends Model
{
    protected $table = 'ai_audits';

    public $timestamps = false;

    protected $fillable = [
        'id_ai_run',
        'id_usuario_aprobador',
        'antes_snapshot',
        'despues_snapshot',
        'cambios_aplicados',
        'created_at'
    ];

    protected $casts = [
        'antes_snapshot' => 'json',
        'despues_snapshot' => 'json',
        'cambios_aplicados' => 'json',
        'created_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(AiRun::class, 'id_ai_run');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'id_usuario_aprobador');
    }
}
