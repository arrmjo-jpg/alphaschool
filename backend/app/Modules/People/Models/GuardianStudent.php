<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Concerns\HasTemporalAssignment;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Modules\Identity\Models\User;
use Carbon\Carbon;
use Database\Factories\GuardianStudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The safety-critical join between the existing Guardian and Student
 * aggregates (docs/DOMAIN_BLUEPRINT.md §11, ADR-0003) -- not a new
 * identity, not a Family aggregate. Carries custody/pickup-authorization
 * state and is effective-dated via HasTemporalAssignment: a change never
 * overwrites history (§7), it closes the current period and opens a new
 * row for the next one.
 *
 * This is HasTemporalAssignment's first real production consumer --
 * the trait itself has only been exercised by its own architecture-level
 * tests until now.
 *
 * `verified_by`/`verified_at` are schema only in this sprint. The real
 * verification workflow (identity-document check, registrar-confirmed,
 * establishing a root of trust reused for every subsequent application by
 * the same guardian) is Phase 4, alongside Admissions.
 *
 * KNOWN LIMITATION, inherited from HasTemporalAssignment itself (Core,
 * Sprint 1.1), not introduced here: the overlap guard is an Eloquent
 * `saving()` hook -- a fetch-then-check-then-write with no row lock and
 * no database-level exclusion constraint. Two concurrent requests
 * creating overlapping relationships for the same guardian-student pair
 * could both pass the check before either write lands. A single
 * consumer should not patch this locally (every future
 * HasTemporalAssignment consumer -- Enrollment, Employment -- would
 * need the identical fix, and three ad hoc implementations is worse
 * than one deferred, correct one); tracked in
 * docs/IMPLEMENTATION_PLAYBOOK.md's Technical Debt Register instead.
 */
class GuardianStudent extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use HasTemporalAssignment;
    use LogsActivity;

    protected $table = 'guardian_student';

    protected static function newFactory(): GuardianStudentFactory
    {
        return GuardianStudentFactory::new();
    }

    /**
     * Symmetric with PersonRelationship's own scope guard (Step 3) --
     * a relationship_type belonging to the person_relationship scope
     * must never be usable here, the same way a guardian_student-scoped
     * type must never be usable in PersonRelationship. Found as a
     * consistency gap during Step 3's review, not a new feature.
     */
    protected static function booted(): void
    {
        static::saving(function (self $guardianStudent): void {
            $type = RelationshipType::find($guardianStudent->relationship_type_id);

            if ($type !== null && $type->scope !== RelationshipType::SCOPE_GUARDIAN_STUDENT) {
                throw new InvalidArgumentException(
                    "GuardianStudent: relationship type '{$type->code}' belongs to the "
                    ."'{$type->scope}' scope, not '".RelationshipType::SCOPE_GUARDIAN_STUDENT."'."
                );
            }
        });
    }

    protected $fillable = [
        'guardian_id',
        'student_id',
        'relationship_type_id',
        'is_primary_contact',
        'is_pickup_authorized',
        'custody_restriction_notes',
        'verified_by_id',
        'verified_at',
        'effective_from',
        'effective_until',
        'status',
        'reason_code_id',
        'ended_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'is_pickup_authorized' => 'boolean',
            'verified_at' => 'datetime',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * A plain 'date' cast does not itself strip the time-of-day from the
     * value stored in the database -- only from what Eloquent later
     * displays back through the accessor. Left unhandled, a row created
     * with effective_from = now() (real current time, not midnight)
     * stores that full timestamp, and HasTemporalAssignment's scopeAsOf()
     * compares against Carbon::parse($date)->startOfDay() -- a row
     * created any time after midnight on its own effective_from date
     * would then compare as "starting after" today at midnight and be
     * wrongly excluded from asOf(today())/active(). Found via this
     * step's own history-retrieval test, not assumed away. Normalizing
     * here, not in HasTemporalAssignment itself, since this is specific
     * to how a consumer sets these two attributes -- worth promoting
     * into the trait once a second consumer (Enrollment, Employment)
     * confirms the same need.
     */
    public function setEffectiveFromAttribute(mixed $value): void
    {
        $this->attributes['effective_from'] = $value !== null ? Carbon::parse($value)->startOfDay() : null;
    }

    public function setEffectiveUntilAttribute(mixed $value): void
    {
        $this->attributes['effective_until'] = $value !== null ? Carbon::parse($value)->startOfDay() : null;
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_id');
    }

    /**
     * Two active relationships between the same guardian-student pair
     * would be a genuine data-integrity problem (which one is
     * authoritative?) -- this is the scope HasTemporalAssignment's
     * overlap guard enforces on save().
     */
    public function temporalScopeAttributes(): array
    {
        return [
            'guardian_id' => $this->guardian_id,
            'student_id' => $this->student_id,
        ];
    }

    public function temporalReasonContext(): string
    {
        return 'guardian_student_relationship';
    }

    /**
     * A deliberate no-op, not an oversight: guardian_id/student_id
     * reference Guardian's and Student's own stable internal ids, never
     * a Person id directly. When a Person merge affects someone who is
     * a Guardian or Student, Guardian::reassignPerson()/
     * Student::reassignPerson() already update that aggregate's own
     * person_id column at its own layer -- Guardian/Student's row id
     * (and therefore this table's guardian_id/student_id values) never
     * changes, so there is nothing here to reassign. Declared explicitly
     * (Addendum C11) precisely so this reasoning is recorded rather than
     * the column silently going unexamined.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId): void
    {
        // Intentionally empty -- see docblock above.
    }

    /**
     * A deliberate no-op: this row holds no personally-identifying field
     * of its own tied directly to a Person id (guardian_id/student_id
     * are Guardian/Student references, handled at their own layer).
     * custody_restriction_notes is free text that could incidentally
     * contain PII, but redacting free-text note content is a distinct,
     * unbuilt governance feature (the same boundary already noted for
     * Universal Notes, Addendum D3) -- not something anonymizePerson()
     * silently attempts here.
     */
    public function anonymizePerson(int $personId): void
    {
        // Intentionally empty -- see docblock above.
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'relationship_type_id',
                'is_primary_contact',
                'is_pickup_authorized',
                'custody_restriction_notes',
                'status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
