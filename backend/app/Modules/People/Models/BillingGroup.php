<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\BillingGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A thin, explicitly administrator-curated grouping of Students for
 * future billing purposes (docs/DOMAIN_BLUEPRINT.md §11, ADR-0003) --
 * "which students bill together". Deliberately an administrative shell
 * ONLY in this sprint: no discount rate, no invoice linkage, no payment
 * allocation, no Finance behavior of any kind. Finance is the intended
 * future consumer, but has no presence here at all.
 *
 * NEVER derived from person_relationships (a graph-derived "sibling"
 * edge must not automatically create a billing unit) and deliberately
 * independent of Household -- the two exist for different
 * administrative purposes and must never be coupled, so Finance can
 * consume this later without Household ever needing to change.
 *
 * Deactivated via `is_active`, never physically deleted -- the same
 * reference/structural-entity treatment as Branch/Role/RelationshipType/
 * Household.
 */
class BillingGroup extends Model
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

    protected static function newFactory(): BillingGroupFactory
    {
        return BillingGroupFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $billingGroup): void {
            throw new RuntimeException(
                'BillingGroup: administrative groupings are never physically deleted -- deactivate instead (is_active = false).'
            );
        });
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'billing_group_members');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name_en', 'name_ar', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
