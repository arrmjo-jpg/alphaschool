<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\OwnedByAggregate;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Events\EnrollmentGraduated;
use App\Modules\People\Events\EnrollmentPromoted;
use App\Modules\People\Events\EnrollmentRepeated;
use App\Modules\People\Events\EnrollmentWithdrawn;
use App\Modules\People\Exceptions\EnrollmentNotActiveException;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Independent top-level identity, not a Student child (frozen
 * docs/DOMAIN_BLUEPRINT.md, Sprint 4.3 Technical Specification) --
 * Attendance/Grades/Fees/Behavior/Report Cards/Learning Participation
 * (BUS-0031) all reference this record directly, never Student. Built
 * as a complete aggregate in Sprint 4.3 (full status value set, the
 * previous/next chain); Sprint 4.4 adds the behavior below
 * (promote/repeat/withdraw/graduate) -- still no schema change.
 * Branch Transfer is deliberately not among them: mutating branch_id in
 * place would overwrite this record's own history, and no historical
 * data model for a mid-period branch change has been designed yet
 * (deferred, see docs/IMPLEMENTATION_PLAYBOOK.md Sprint 4.4 entry).
 *
 * Suspension is deliberately not a status value here -- it's a
 * sub-status on the SAME Enrollment, tracked via SuspensionRecord,
 * never a new Enrollment row.
 *
 * Chain Invariant (Sprint 4.4 Architecture Pass, explicit user
 * requirement, 2026-07-27): every Enrollment has at most one
 * next_enrollment_id and at most one previous_enrollment_id -- no
 * Action may produce a branching chain or a cycle. This holds
 * structurally, not by a separate runtime check: promote()/repeat() are
 * the only methods that ever set next_enrollment_id, both always create
 * a brand-new row as "next" (never re-pointing at an existing one), and
 * both refuse to run at all unless this row is still STATUS_ACTIVE --
 * an ACTIVE row can never already have a next_enrollment_id, since
 * setting one only ever happens together with leaving ACTIVE, in the
 * same save(). A row can therefore never acquire a second "next," and
 * a "previous" is only ever assigned once, at creation, from the row
 * that just promoted/repeated into it.
 *
 * OwnedByAggregate(Student), not ReassignsIdentityReferences/
 * RedactsPersonalData directly: caught by
 * IdentityMaintenanceSchemaDeclarationTest (student_id matches its
 * column scanner, the same as person_id/guardian_id/employee_id). The
 * claim is honest, not a formality -- a Person merge only ever updates
 * Student.person_id; the Student row's own primary key never moves, so
 * Enrollment.student_id never needs updating during a merge at all.
 */
class Enrollment extends Model implements OwnedByAggregate
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PROMOTED = 'promoted';

    public const STATUS_REPEATED = 'repeated';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_GRADUATED = 'graduated';

    protected $fillable = [
        'student_id', 'academic_year_id', 'branch_id', 'grade_level_id',
        'status', 'previous_enrollment_id', 'next_enrollment_id',
    ];

    protected static function newFactory(): EnrollmentFactory
    {
        return EnrollmentFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Deliberately no academicYear()/gradeLevel() relation methods
    // (ADR-0026) -- People is Foundation, AcademicYear/GradeLevel are
    // Domain (Academic), and deptrac.yaml forbids Foundation depending
    // on Domain regardless of which class does it. The academic_year_id
    // /grade_level_id columns above are plain FKs; code that needs the
    // actual row queries Academic's own models directly by ID, from
    // Domain-side code (Admissions/Academic), never through a relation
    // declared here. Confirmed unused anywhere in the codebase before
    // removal.

    public function suspensionRecords(): HasMany
    {
        return $this->hasMany(SuspensionRecord::class);
    }

    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_enrollment_id');
    }

    public function nextEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_enrollment_id');
    }

    public static function owningAggregate(): string
    {
        return Student::class;
    }

    /**
     * Called by Academic's PromoteEnrollmentAction (Sprint 4.4) -- takes
     * plain IDs, never a GradeLevel/AcademicYear instance (ADR-0026).
     * Resolving "what's the next grade" and "is the target year open" is
     * the caller's job; this method only performs the write, atomically
     * with the closing status change on $this.
     */
    public function promote(int $nextAcademicYearId, int $nextGradeLevelId): self
    {
        $this->assertActive('promote');

        $next = self::create([
            'student_id' => $this->student_id,
            'academic_year_id' => $nextAcademicYearId,
            'branch_id' => $this->branch_id,
            'grade_level_id' => $nextGradeLevelId,
            'status' => self::STATUS_ACTIVE,
            'previous_enrollment_id' => $this->id,
        ]);

        $this->status = self::STATUS_PROMOTED;
        $this->next_enrollment_id = $next->id;
        $this->save();

        DB::afterCommit(function () use ($next): void {
            EnrollmentPromoted::dispatch($this, $next);
        });

        return $next;
    }

    /**
     * Same grade_level_id, new academic_year_id -- the student repeats
     * the grade. Mirrors promote() exactly otherwise.
     */
    public function repeat(int $nextAcademicYearId): self
    {
        $this->assertActive('repeat');

        $next = self::create([
            'student_id' => $this->student_id,
            'academic_year_id' => $nextAcademicYearId,
            'branch_id' => $this->branch_id,
            'grade_level_id' => $this->grade_level_id,
            'status' => self::STATUS_ACTIVE,
            'previous_enrollment_id' => $this->id,
        ]);

        $this->status = self::STATUS_REPEATED;
        $this->next_enrollment_id = $next->id;
        $this->save();

        DB::afterCommit(function () use ($next): void {
            EnrollmentRepeated::dispatch($this, $next);
        });

        return $next;
    }

    /**
     * Terminal -- no new Enrollment row, no chain entry.
     * students.current_enrollment_id deliberately keeps pointing at this
     * (now withdrawn) row: it remains "the last enrollment period that
     * mattered" until a future re-conversion reactivates the Student via
     * ConvertApplicantToStudentAction's existing returning-guardian path
     * (Sprint 4.3).
     */
    public function withdraw(): void
    {
        $this->assertActive('withdraw');

        $this->status = self::STATUS_WITHDRAWN;
        $this->save();

        DB::afterCommit(function (): void {
            EnrollmentWithdrawn::dispatch($this);
        });
    }

    /**
     * Terminal, same shape as withdraw(). Whether this GradeLevel is
     * actually the final one in sequence is Academic's own check
     * (GraduateEnrollmentAction, via AcademicCatalogService) -- this
     * method has no grade-sequence awareness of its own (ADR-0026).
     */
    public function graduate(): void
    {
        $this->assertActive('graduate');

        $this->status = self::STATUS_GRADUATED;
        $this->save();

        DB::afterCommit(function (): void {
            EnrollmentGraduated::dispatch($this);
        });
    }

    /**
     * The single enforcement point for both the terminal-state rule
     * (Withdrawal/Graduation are final -- Sprint 4.4 Architecture Pass,
     * explicit user requirement) and the Chain Invariant documented on
     * this class -- throws, never no-ops, unlike Applicant::convert()'s
     * own defensive-backstop pattern (Sprint 4.3): these four
     * transitions are always administrator-triggered, so a call against
     * an already-decided Enrollment is a real caller mistake that must
     * surface, not disappear.
     */
    private function assertActive(string $attemptedTransition): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new EnrollmentNotActiveException($this, $attemptedTransition);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'previous_enrollment_id', 'next_enrollment_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
