<?php

use App\Modules\Media\Console\Commands\PurgeTemporaryMedia;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// docs/DOMAIN_BLUEPRINT.md §12: mandatory scheduled purge, not optional --
// unbounded ephemeral-tier growth is a named performance risk.
Schedule::command(PurgeTemporaryMedia::class)->daily();
