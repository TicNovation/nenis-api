<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbArticle extends Model
{
    protected $table = 'kb_articles';

    protected $fillable = [
        'slug',
        'titulo',
        'categoria',
        'contenido',
        'link_fuente',
        'keywords',
        'es_publico',
        'estatus',
        'version'
    ];

    protected $casts = [
        'keywords' => 'json',
        'es_publico' => 'boolean',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
