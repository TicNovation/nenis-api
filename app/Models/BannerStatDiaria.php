<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerStatDiaria extends Model
{
    protected $table = 'banners_stats_diarias';

    public $timestamps = false;

    protected $fillable = [
        'id_banner',
        'fecha',
        'impresiones',
        'clicks',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}
