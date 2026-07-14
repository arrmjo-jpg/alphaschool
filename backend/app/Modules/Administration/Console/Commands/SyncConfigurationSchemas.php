<?php

namespace App\Modules\Administration\Console\Commands;

use App\Modules\Administration\Services\ConfigurationRegistry;
use Illuminate\Console\Command;

/**
 * The deploy-time registration moment
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 2: "a deploy-time manifest, code-reviewed... registered on
 * deploy") -- run as part of the deployment pipeline, the same
 * conceptual step as `migrate`, never invoked as a side effect of an
 * HTTP request.
 */
class SyncConfigurationSchemas extends Command
{
    protected $signature = 'administration:sync-settings';

    protected $description = 'Sync every registered DeclaresSettingsSchema implementer into configuration_definitions';

    public function handle(ConfigurationRegistry $registry): int
    {
        $result = $registry->sync();

        $this->info(sprintf('Synced %d configuration key(s).', count($result['synced'])));

        foreach ($result['flagged'] as $flag) {
            $this->warn("Flagged (acknowledged): {$flag}");
        }

        return self::SUCCESS;
    }
}
