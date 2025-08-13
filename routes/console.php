<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('php artisan app:trail-expiry-notification')->daily();
Schedule::command('php artisan app:subscription-expiry-mail')->daily();
