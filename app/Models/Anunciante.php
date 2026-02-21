<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Anunciante extends Model
{
    use HasFactory;

    protected $table = 'anunciantes';

    protected $fillable = [
        'nombre',
        'correo',
        'telefono',
        'activo',
    ];
}
