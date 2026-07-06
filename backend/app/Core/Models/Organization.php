<?php

namespace App\Core\Models;

use App\Core\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendor/licensing identity for this dedicated instance
 * (docs/DOMAIN_BLUEPRINT.md Addendum A2) -- legal/display name, module
 * licensing, support-contract metadata. Deliberately NOT a business
 * hierarchy layer above School (that's a domain/academic concept for a
 * future multi-school scenario, kept separate on purpose).
 *
 * Passes Core's domain-agnosticism test (Addendum B1): vendor/licensing
 * identity is a concept any dedicated-instance B2B product would have,
 * not something specific to running a school.
 *
 * Exactly one row is expected per instance (ADR-0006: dedicated instance
 * per customer, no tenant_id anywhere).
 */
class Organization extends Model
{
    use HasPublicId;

    protected $fillable = [
        'legal_name', 'display_name', 'support_contract_reference', 'support_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'support_expires_at' => 'date',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(OrganizationModule::class);
    }

    public function hasLicensed(string $moduleCode): bool
    {
        return $this->modules()
            ->where('module_code', $moduleCode)
            ->where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('licensed_until')->orWhere('licensed_until', '>=', now()->toDateString());
            })
            ->exists();
    }
}
