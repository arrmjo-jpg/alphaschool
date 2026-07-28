<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Concerns\HasTemporalAssignment;
use App\Core\Contracts\OwnedByAggregate;
use App\Modules\People\Models\Employee;
use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Employee <-> SubjectOffering teacher assignment (Subject Offering +
 * Timetables Architecture Pass, explicit user requirement: reuse the
 * existing Assignment Engine pattern, do NOT merge with Homeroom --
 * a homeroom teacher and a subject teacher are different facts about an
 * Employee, tracked independently). Mirrors HomeroomAssignment's own
 * shape exactly, scoped to subject_offering_id instead of section_id.
 *
 * Single-cardinality assumption, explicitly flagged (not a permanent
 * constraint): temporalScopeAttributes() enforces one active teacher per
 * SubjectOffering at a time, the same "one active X per Y" shape as
 * HomeroomAssignment/SectionAssignment. Real-world co-teaching (two
 * teachers sharing one SubjectOffering) is out of scope for v1 -- if a
 * genuine need surfaces, it changes temporalScopeAttributes()'s
 * uniqueness semantics, not this model's overall shape.
 *
 * OwnedByAggregate(Employee), not ReassignsIdentityReferences/
 * RedactsPersonalData directly -- employee_id matches
 * IdentityMaintenanceSchemaDeclarationTest's column scanner, same
 * reasoning as HomeroomAssignment's own declaration.
 */
class TeacherAssignment extends Model implements OwnedByAggregate
{
    use HasFactory;
    use HasPublicId;
    use HasTemporalAssignment;
    use LogsActivity;

    protected $fillable = [
        'employee_id', 'subject_offering_id', 'effective_from', 'effective_until',
        'status', 'reason_code_id', 'ended_by_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function newFactory(): TeacherAssignmentFactory
    {
        return TeacherAssignmentFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function subjectOffering(): BelongsTo
    {
        return $this->belongsTo(SubjectOffering::class);
    }

    /**
     * One active teacher per SubjectOffering at a time -- see the class
     * docblock's single-cardinality assumption.
     */
    public function temporalScopeAttributes(): array
    {
        return ['subject_offering_id' => $this->subject_offering_id];
    }

    public function temporalReasonContext(): string
    {
        return 'subject_teacher_assignment';
    }

    public static function owningAggregate(): string
    {
        return Employee::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
