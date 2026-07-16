<?php

namespace App\Modules\Administration\Services;

use App\Core\Contracts\HealthCheckable;
use Illuminate\Support\Facades\Cache;

/**
 * Playbook Phase 2's "Health-Check Runner v1 (synchronous)" -- resolves
 * a Provider through App\Modules\Administration\Services\ProviderManager
 * (table-driven, no vendor conditionals) and, when the resolved instance
 * implements App\Core\Contracts\HealthCheckable, invokes it. Results are
 * cached short-TTL (Playbook Phase 2: "the first legitimate caching need
 * in this roadmap") -- this caches only the boolean health outcome,
 * never a credential value, so it carries none of ADR-0021 Decision 1's
 * shadow-state risk.
 */
class HealthCheckRunner
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly ProviderManager $providerManager,
    ) {}

    /**
     * @return array{slot_key: string, status: 'healthy'|'unhealthy'|'not_checkable', cached: bool}
     */
    public function check(string $slotKey): array
    {
        $cacheKey = "administration.provider-health.{$slotKey}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return ['slot_key' => $slotKey, 'status' => $cached, 'cached' => true];
        }

        $provider = $this->providerManager->resolve($slotKey);

        if (! $provider instanceof HealthCheckable) {
            return ['slot_key' => $slotKey, 'status' => 'not_checkable', 'cached' => false];
        }

        $status = $provider->healthCheck() ? 'healthy' : 'unhealthy';

        Cache::put($cacheKey, $status, self::CACHE_TTL_SECONDS);

        return ['slot_key' => $slotKey, 'status' => $status, 'cached' => false];
    }
}
