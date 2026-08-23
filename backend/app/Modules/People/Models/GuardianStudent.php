<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Concerns\HasTemporalAssignment;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\Identity\Models\User;
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
 * This was HasTemporalAssignment's first real production consumer --
 * the trait itself had only been exercised by its own architecture-level
 * tests before this. The concurrency-safety and date-boundary-
 * normalization fixes this model's own docblock used to document as
 * known limitations are now handled centrally by the trait itself
 * (Phase 5 Sprint A0) -- this model no longer carries any local
 * mutators or stopgap notes for either.
 *
 * `verified_by`/`verified_at` are schema only in this sprint. The real
 * verification workflow (identity-document check, registrar-confirmed,
 * establishing a root of trust reused for every subsequent application by
 * the same guardian) is Phase 4, alongside Admissions.
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
    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void
    {
        // Intentionally empty -- see docblock above. Nothing here can
        // ever be structurally invalid with respect to a Person merge,
        // so $dryRun is always a no-op success too.
    }

    /**
     * @return ReassignmentImpact[]
     */
    public function previewReassignment(int $oldPersonId, int $newPersonId): array
    {
        return [];
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
