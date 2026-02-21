<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    protected $table = 'ciudades';

    protected $fillable = [
        'id_estado',
        'nombre',
        'slug',
    ];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }
}
