<?php

namespace App\Modules\Administration\Console\Commands;

use App\Modules\Administration\Services\ProviderRegistry;
use Illuminate\Console\Command;

/**
 * The Provider Registry's deploy-time registration moment
 * (docs/adr/0019-integration-platform-architecture.md Decision 1) --
 * mirrors App\Modules\Administration\Console\Commands\
 * SyncConfigurationSchemas exactly: run as part of the deployment
 * pipeline, never invoked as a side effect of an HTTP request.
 */
class SyncProviderSlots extends Command
{
    protected $signature = 'administration:sync-providers';

    protected $description = 'Sync every registered DeclaresProviderSlots implementer into provider_registrations';

    public function handle(ProviderRegistry $registry): int
    {
        $result = $registry->sync();

        $this->info(sprintf('Synced %d provider slot(s).', count($result['synced'])));

        return self::SUCCESS;
    }
}
