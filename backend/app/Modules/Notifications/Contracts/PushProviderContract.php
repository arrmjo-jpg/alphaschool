<?php

namespace App\Modules\Notifications\Contracts;

/**
 * The capability contract every Push-category Provider implements
 * (docs/adr/0019-integration-platform-architecture.md Decision 1). A
 * deliberately different shape from EmailProviderContract -- proving the
 * Provider Registry is generic across genuinely different capability
 * contracts, not merely different vendors of the same one.
 */
interface PushProviderContract
{
    public function sendPush(string $deviceToken, string $title, string $body): bool;
}
