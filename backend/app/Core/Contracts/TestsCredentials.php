<?php

namespace App\Core\Contracts;

/**
 * A Provider's optional contract for validating credentials that have
 * not been written to the Vault yet (docs/ADMIN_DESIGN_SYSTEM.md
 * §27.5's Edit->Test->Save rule, pre-freeze amendment) -- deliberately
 * a sibling to HealthCheckable, not a parameter added to
 * healthCheck(). HealthCheckable::healthCheck() always resolves the
 * already-persisted credential via the Vault by design (Playbook Phase
 * 2's "genuinely different from a live vendor call, a conservative
 * completeness check"); overloading that same method to sometimes
 * accept unsaved values would blur two operations with genuinely
 * different guarantees -- one reads what's stored, this one never
 * touches storage at all.
 *
 * Implementing this is optional, exactly like HealthCheckable -- a
 * Provider with no meaningful pre-save check simply does not implement
 * it, and the Administration Workspace's Test Connection control is
 * absent rather than shown non-functional (the same "never a fake
 * control" discipline documented throughout docs/ADMIN_DESIGN_SYSTEM.md).
 */
interface TestsCredentials
{
    /**
     * @param  array<string, mixed>  $credentials  The exact shape ProviderCredentialVault::assertCredentialShape() would require for a real write -- never partial, never persisted regardless of the result.
     */
    public function testCredentials(array $credentials): bool;
}
