<?php

namespace App\Modules\Media\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purges files from the `temporary` disk tier older than the configured
 * threshold (docs/DOMAIN_BLUEPRINT.md §12: "unbounded ephemeral-tier
 * growth" is a named performance risk -- this command is the mandatory
 * mitigation, not an afterthought).
 *
 * Operates directly on the filesystem, not on Media database records --
 * per the Media Architecture decision, exports/imports/temporary uploads
 * deliberately do NOT go through full Media Library machinery, so there
 * is no Media row to query for most files on this disk.
 */
class PurgeTemporaryMedia extends Command
{
    protected $signature = 'media:purge-temporary
        {--hours=24 : Delete files older than this many hours}
        {--dry-run : List what would be deleted without deleting anything}';

    protected $description = 'Purge files from the temporary media disk older than the given threshold';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('temporary');

        $stale = collect($disk->allFiles())
            ->filter(fn (string $path) => $disk->lastModified($path) < $cutoff->getTimestamp());

        if ($stale->isEmpty()) {
            $this->info('No stale temporary files found.');

            return self::SUCCESS;
        }

        foreach ($stale as $path) {
            if ($dryRun) {
                $this->line("Would delete: {$path}");

                continue;
            }

            $disk->delete($path);
            $this->line("Deleted: {$path}");
        }

        $this->info(($dryRun ? 'Would purge ' : 'Purged ').$stale->count().' file(s) older than '.$this->option('hours').' hour(s).');

        return self::SUCCESS;
    }
}
