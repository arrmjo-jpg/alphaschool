<?php

namespace App\Core\Contracts;

/**
 * The Provider SDK's health-check invocation shape
 * (docs/adr/0019-integration-platform-architecture.md Decision 1's
 * "health-check callback"). ProviderSlotDefinition (Phase 1) carries an
 * optional raw `healthCheck` callable on the *declaration*, but a
 * callable cannot be persisted to the Provider Registry's table -- this
 * interface is what a resolved Provider *instance* implements so
 * App\Modules\Administration\Services\HealthCheckRunner has one
 * uniform, reflection-free way to invoke it, regardless of vendor.
 * Implementing this is optional; a Provider with no meaningful health
 * probe (e.g. a pure data-shape credential) simply does not implement
 * it, and the Runner records "not checkable" rather than failing.
 */
interface HealthCheckable
{
    public function healthCheck(): bool;
}
