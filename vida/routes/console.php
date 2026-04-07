<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Agenda\Jobs\SlotExpirationJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agenda: expira slots no consumidos al final de cada día laboral
Schedule::job(SlotExpirationJob::class)->dailyAt('20:00');
