<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A thin, explicitly administrator-curated grouping of people
 * (docs/DOMAIN_BLUEPRINT.md §11, ADR-0003) -- NOT a family model, and
 * NEVER derived from or coupled to person_relationships. A household is
 * whatever an administrator decides it is (e.g. a shared residence for
 * mailing/logistics purposes); the "true" relationship graph may
 * legitimately disagree with it (a remarriage might create a graph
 * sibling-edge the school never intends as a household unit), and
 * that's by design, not a data-quality gap.
 *
 * Deliberately independent of BillingGroup -- the two exist for
 * different administrative purposes and must never be coupled, so
 * Finance can consume BillingGroup later without Household ever
 * needing to change.
 *
 * Deactivated via `is_active`, never physically deleted -- the same
 * reference/structural-entity treatment as Branch/Role/RelationshipType.
 */
class Household extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = ['name_en', 'name_ar', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): HouseholdFactory
    {
        return HouseholdFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $household): void {
            throw new RuntimeException(
                'Household: administrative groupings are never physically deleted -- deactivate instead (is_active = false).'
            );
        });
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'household_members');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name_en', 'name_ar', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
