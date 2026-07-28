<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\OwnedByAggregate;
use App\Modules\Identity\Models\Branch;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Independent top-level identity, not a Student child (frozen
 * docs/DOMAIN_BLUEPRINT.md, Sprint 4.3 Technical Specification) --
 * Attendance/Grades/Fees/Behavior/Report Cards/Learning Participation
 * (BUS-0031) all reference this record directly, never Student. Built
 * as a complete aggregate this sprint (full status value set, the
 * previous/next chain) per explicit architectural instruction --
 * Sprint 4.4 adds the actions that reach promoted/repeated/transferred/
 * withdrawn/graduated, not new schema.
 *
 * Suspension is deliberately not a status value here -- it's a
 * sub-status on the SAME Enrollment, tracked via SuspensionRecord,
 * never a new Enrollment row.
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'previous_enrollment_id', 'next_enrollment_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
