<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The permanent identity anchor for "this Person has ever worked here"
 * (docs/DOMAIN_BLUEPRINT.md §3). Owns ONLY a coarse lifecycle status --
 * hire date, leave date, Position, and Salary belong to Employment
 * (ADR-0005, Phase 6), never to Employee directly. A rehire opens a new
 * Employment period against this same Employee row; Employee is never
 * recreated.
 *
 * Never branch-scoped (Addendum B6): branch relevance arrives with
 * branch membership owned by Employment (ADR-0005, Phase 6), never a
 * column here. Deliberately not built as a Sprint 2.4 placeholder --
 * see the Sprint 2.4 execution plan's Step 4 deferment: building this
 * persistence model before Employment (its owning aggregate) exists
 * would be prediction, not promotion (Addendum B1).
 */
class Employee extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = ['person_id', 'lifecycle_status'];

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Trivial per the Sprint 2.4 execution plan: Employee holds only its
     * own person_id at this point (no child entities exist yet --
     * employee_branches was deferred to Phase 6, see the docblock above).
     * Assumes the caller (Identity Maintenance's Merge, Phase 3) has
     * already validated the structural conflict of both Persons holding
     * an Employee row before calling this, per Addendum C9.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId): void
    {
        static::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
    }

    /**
     * A deliberate no-op: Employee holds no personally-identifying field
     * of its own (lifecycle_status is not PII) -- there is nothing here
     * for Person's future anonymization (Phase 3) to redact.
     */
    public function anonymizePerson(int $personId): void
    {
        //
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['lifecycle_status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
