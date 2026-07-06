<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Per-module license state for an Organization -- deliberately relational
 * (not a JSON array on Organization), since `enabled` + `licensed_until`
 * are structured facts with real query needs (a renewal-reminder job, an
 * expiring-license banner), the same reasoning that already put Money in
 * real integer columns instead of a formatted string.
 *
 * Domain modules only (docs/DOMAIN_BLUEPRINT.md §1) -- Foundation
 * modules are the base product and are never gated, so they never get a
 * row here.
 */
class OrganizationModule extends Model
{
    /**
     * The fixed, developer-maintained list of licensable Domain modules
     * (Blueprint §1). Grows only when a new Domain module's own first
     * sprint ships -- never admin-editable at runtime, the same
     * "enum when only developers extend the set" treatment already
     * applied to Gender/lookup-vs-enum decisions elsewhere.
     */
    public const MODULE_CODES = [
        'admissions', 'academic', 'finance', 'hr', 'inventory',
        'library', 'transportation', 'lms', 'reporting',
    ];

    protected $fillable = ['organization_id', 'module_code', 'enabled', 'licensed_until'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'licensed_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $organizationModule): void {
            if (! in_array($organizationModule->module_code, self::MODULE_CODES, true)) {
                throw new InvalidArgumentException(sprintf(
                    "OrganizationModule: '%s' is not a recognized Domain module code.",
                    $organizationModule->module_code,
                ));
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isCurrentlyLicensed(): bool
    {
        return $this->enabled && ($this->licensed_until === null || $this->licensed_until->isFuture() || $this->licensed_until->isToday());
    }
}
