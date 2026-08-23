<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Concerns\HasTemporalAssignment;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Enrollment;
use Database\Factories\SectionAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The Student <-> Section membership fact (Phase 5 Architecture Pass,
 * DOMAIN_BLUEPRINT.md §4's "Section-assignment history" on Enrollment).
 * Anchored on Enrollment, not Student directly -- both Enrollment and
 * Section are independently scoped to the same (branch, academic_year,
 * grade_level) triple, and the Consistency Invariant below is what
 * actually enforces that the two agree, not merely an assumption about
 * how this class happens to get called.
 *
 * Effective-dated via HasTemporalAssignment -- DOMAIN_BLUEPRINT.md's own
 * wording ("Section-assignment *history*") anticipates mid-year Section
 * changes as a real case, not an edge case; closeAssignment() (inherited)
 * is how a student moves from one Section to another within the same
 * Enrollment, without overwriting the historical fact of where they were
 * before.
 *
 * No ReassignsIdentityReferences/RedactsPersonalData/OwnedByAggregate
 * declaration -- enrollment_id/section_id do not match
 * IdentityMaintenanceSchemaDeclarationTest's column scanner (person_id/
 * *_person_id/student_id/employee_id/guardian_id only), the same reason
 * SuspensionRecord (Sprint 4.3) needs none either.
 */
class SectionAssignment extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasTemporalAssignment;
    use LogsActivity;

    protected $fillable = [
        'enrollment_id', 'section_id', 'effective_from', 'effective_until',
        'status', 'reason_code_id', 'ended_by_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function newFactory(): SectionAssignmentFactory
    {
        return SectionAssignmentFactory::new();
    }

    /**
     * The Consistency Invariant (Phase 5 Architecture Pass, explicit
     * user requirement, 2026-07-28): a SectionAssignment must never link
     * an Enrollment to a Section whose branch/academic_year/grade_level
     * don't all agree. Enforced here, in SectionAssignment's own
     * creation path -- not left as an implicit consequence of "it
     * references Enrollment" -- because SectionAssignment is an
     * independent aggregate that could in principle be constructed from
     * anywhere, and this guard must hold regardless of the caller.
     * Mirrors GuardianStudent's own relationship_type-scope check
     * (Sprint 2.5 Step 2) in shape: a static::saving() guard, not a
     * database constraint (the three columns being compared live on two
     * different tables, which a FK/CHECK constraint cannot express).
     */
    protected static function booted(): void
    {
        static::saving(function (self $sectionAssignment): void {
            $enrollment = Enrollment::findOrFail($sectionAssignment->enrollment_id);
            $section = Section::findOrFail($sectionAssignment->section_id);

            if ($enrollment->branch_id !== $section->branch_id
                || $enrollment->academic_year_id !== $section->academic_year_id
                || $enrollment->grade_level_id !== $section->grade_level_id) {
                throw new InvalidArgumentException(
                    "SectionAssignment: Enrollment ({$enrollment->public_id}) and Section ({$section->public_id}) ".
                    'do not agree on branch/academic_year/grade_level -- a student cannot be assigned to a Section '.
                    'outside their own Enrollment\'s branch, academic year, or grade level.'
                );
            }
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Not on HasTemporalAssignment itself despite ended_by_id being a
     * shared column (like reason_code_id's reasonCode() relation, which
     * IS on the trait) -- User lives in the Identity Foundation module,
     * and Core must never depend on a Foundation module (deptrac's
     * CoreBoundaryTest). Same reasoning as HomeroomAssignment's own
     * copy of this relation.
     */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_id');
    }

    /**
     * One active Section per Enrollment at a time -- a student cannot
     * simultaneously belong to two Sections within the same enrollment
     * period.
     */
    public function temporalScopeAttributes(): array
    {
        return ['enrollment_id' => $this->enrollment_id];
    }

    public function temporalReasonContext(): string
    {
        return 'section_assignment';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
