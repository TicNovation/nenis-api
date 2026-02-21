<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('app:backup-database-to-s3 --retention-days=14')
    ->dailyAt('06:00')
    ->withoutOverlapping(180)
    ->runInBackground();