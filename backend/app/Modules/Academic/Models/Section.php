<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Modules\Identity\Models\Branch;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Academic's own Master Data (academic.md §3), per-year -- not a
 * permanent catalog like GradeLevel (Phase 5 Architecture Pass,
 * explicit decision). "Grade 5 Section A" means a specific roster for
 * one Academic Year; next year's Grade 5 Section A is a different row
 * entirely, scoped by the (branch_id, academic_year_id, grade_level_id,
 * name) unique constraint. This sidesteps an entire category of "does
 * the roster/teacher/capacity carry over" ambiguity by construction.
 *
 * Lives in Academic (Domain), alongside AcademicYear/GradeLevel/
 * BranchGradeLevel -- its own relations to them are Domain -> Domain
 * (unrestricted under the current deptrac collector, per ADR-0026's own
 * disclosed gap), unlike Enrollment (Foundation), which may never hold
 * a typed relation to these same models.
 *
 * No section_id column exists on enrollments -- section membership is
 * SectionAssignment's own fact (Phase 5 Architecture Pass), not
 * something Enrollment carries, keeping Enrollment's already-frozen
 * shape untouched.
 */
class Section extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = [
        'branch_id', 'academic_year_id', 'grade_level_id', 'name', 'capacity', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): SectionFactory
    {
        return SectionFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(SectionAssignment::class);
    }

    public function homeroomAssignments(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'capacity', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
