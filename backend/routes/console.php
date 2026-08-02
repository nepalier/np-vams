<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Section 36 events that need a scheduled check rather than a model
// observer or workflow transition -- both run once daily. cPanel's
// cron entry (`php artisan schedule:run`, once/minute) is what actually
// triggers these; see the deployment README.
Schedule::command('npvams:check-overdue-invoices')->daily();
Schedule::command('npvams:check-expiring-registrations')->daily();
