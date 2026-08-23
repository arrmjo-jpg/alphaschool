<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Concerns\HasTemporalAssignment;
use App\Core\Contracts\OwnedByAggregate;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Employee;
use Database\Factories\HomeroomAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Employee <-> Section homeroom teacher assignment, frozen decision
 * BUS-0019 (Academic Assignment Model): "Teacher Assignment, Homeroom
 * assignment... modeled as effective-dated Assignment aggregates, in the
 * same category as Enrollment and Employment... reusing the existing
 * Assignment Engine pattern directly" -- explicitly rejecting a mutable
 * section.homeroom_teacher_id field. This is HasTemporalAssignment's own
 * worked example in its class docblock ("two teachers cannot both be the
 * active homeroom teacher of the same section at once").
 *
 * OwnedByAggregate(Employee), not ReassignsIdentityReferences/
 * RedactsPersonalData directly: employee_id matches
 * IdentityMaintenanceSchemaDeclarationTest's column scanner, the same as
 * Enrollment's own student_id -- a Person merge only ever updates
 * Employee.person_id; Employee's own row id never moves, so
 * HomeroomAssignment.employee_id never needs updating during a merge.
 */
class HomeroomAssignment extends Model implements OwnedByAggregate
{
    use HasFactory;
    use HasPublicId;
    use HasTemporalAssignment;
    use LogsActivity;

    protected $fillable = [
        'employee_id', 'section_id', 'effective_from', 'effective_until',
        'status', 'reason_code_id', 'ended_by_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function newFactory(): HomeroomAssignmentFactory
    {
        return HomeroomAssignmentFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Not on HasTemporalAssignment itself despite ended_by_id being a
     * shared column (like reason_code_id's reasonCode() relation) --
     * User lives in the Identity Foundation module, and Core must never
     * depend on a Foundation module (deptrac's CoreBoundaryTest). Each
     * Domain-module consumer declares its own copy of this one-liner.
     */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_id');
    }

    /**
     * One active homeroom teacher per Section at a time -- the trait's
     * own worked example, verbatim.
     */
    public function temporalScopeAttributes(): array
    {
        return ['section_id' => $this->section_id];
    }

    public function temporalReasonContext(): string
    {
        return 'homeroom_teacher_assignment';
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
