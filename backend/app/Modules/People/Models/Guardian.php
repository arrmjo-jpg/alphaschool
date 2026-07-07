<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The permanent identity anchor for "this Person has ever held a
 * guardianship relationship to a Student" (docs/DOMAIN_BLUEPRINT.md §3).
 * Owns ONLY a coarse lifecycle status -- which students, relationship
 * type, custody/pickup authorization, notification defaults, and portal
 * access all belong to guardian_student (Sprint 2.5, ADR-0003), never to
 * Guardian directly. Deliberately the thinnest of the three context
 * shells: its lifecycle is genuinely flatter than Employee/Student's,
 * and is not widened to match them for symmetry's own sake.
 *
 * Never branch-scoped (Addendum B6): a guardian's children may be
 * enrolled at different branches, so branch relevance is never a column
 * here.
 */
class Guardian extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = ['person_id', 'lifecycle_status'];

    protected static function newFactory(): GuardianFactory
    {
        return GuardianFactory::new();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Trivial per the Sprint 2.4 execution plan and the architectural
     * clarification agreed for Employee/Student: Guardian holds only its
     * own person_id at this point. Assumes the caller (Identity
     * Maintenance's Merge, Phase 3) has already validated the structural
     * conflict of both Persons holding a Guardian row, and that it will
     * be called at most once per successful merge -- Guardian does not
     * defend against either case itself.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId): void
    {
        static::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
    }

    /**
     * A deliberate no-op: Guardian holds no personally-identifying field
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
