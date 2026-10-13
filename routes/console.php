<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Requires a cron entry on the server:
 *   * * * * * cd /path/to/pal-market && php artisan schedule:run >> /dev/null 2>&1
 */

Schedule::command('app:expire-supplier-quotations')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('app:expire-buyer-requests')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command('auth:clear-resets')->everySixHours();
