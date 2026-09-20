<?php

use App\Models\SecurityEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SecurityEvent rows hold IP addresses, so they expire.
Schedule::command('model:prune', ['--model' => [SecurityEvent::class]])->daily();
