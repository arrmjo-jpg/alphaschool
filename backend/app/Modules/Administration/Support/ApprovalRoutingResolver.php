<?php

namespace App\Modules\Administration\Support;

/**
 * Translates a governance permission name into the step definitions
 * App\Core\Services\ApprovalEngine already understands -- the same
 * pattern App\Modules\IdentityMaintenance\Support\ApprovalRoutingResolver
 * already proved in Sprint 3.2, independently re-declared here rather
 * than shared, per Blueprint Addendum B1's promotion test: this is only
 * the SECOND real consumer of this shape, and B1 promotes a concern to
 * Core only once a THIRD independent module needs it. Administration
 * Platform's own deptrac ruleset (Administration: [Core] only) also
 * forbids depending on App\Modules\IdentityMaintenance directly, so a
 * shared class is not even available to reuse without first promoting
 * it -- which this phase deliberately does not do speculatively.
 *
 * Provider-agnostic by design, exactly like its IdentityMaintenance
 * counterpart: this interface references only plain strings, never a
 * Spatie\Permission\* class.
 */
interface ApprovalRoutingResolver
{
    /**
     * @return array<int, array{required_role: string}>
     */
    public function resolveSteps(string $permission, string $guard = 'sanctum'): array;
}
