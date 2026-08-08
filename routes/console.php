<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shipments:notify-delays')
    ->everyFifteenMinutes()
    ->name('shipments-notify-delays')
    ->withoutOverlapping(60)
    ->onOneServer();
