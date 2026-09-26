<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console routes
|--------------------------------------------------------------------------
|
| Ad-hoc operator commands live in app/Console/Commands and are auto-discovered.
| This file holds the schedule.
|
| Retention anonymises closed leads older than the configured window, mirroring
| Safari_Lead_Save::purge_expired on the WordPress side. It needs a scheduler:
|
|   php artisan schedule:work                                  (development)
|   * * * * * cd /path/to/backend && php artisan schedule:run  (production)
|
*/

Schedule::job(new App\Jobs\AnonymiseExpiredLeads)
    ->dailyAt('03:17')
    ->name('safari:lead-retention')
    ->withoutOverlapping();
