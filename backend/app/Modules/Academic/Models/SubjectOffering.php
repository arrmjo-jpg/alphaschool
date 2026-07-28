<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Modules\Academic\Exceptions\TermAcademicYearMismatchException;
use Database\Factories\SubjectOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A Subject taught to a Section in a Term (Subject Offering + Timetables
 * Architecture Pass) -- BUS-0018's formula
 * (Subject x Term x Section x Teacher x Room x Schedule) minus Teacher/
 * Room/Schedule, which are their own aggregates (TeacherAssignment;
 * Room/Timetable deliberately deferred, see below). Identity is the
 * (subject_id, section_id, term_id) triple.
 *
 * No teacher_id -- Teacher Assignment is TeacherAssignment's own
 * effective-dated aggregate, mirroring Section/HomeroomAssignment's own
 * precedent; a subject-level teacher can change mid-term without
 * mutating this row.
 *
 * No status/is_active field at all -- explicit user decision (Subject
 * Offering + Timetables Architecture Pass review): SubjectOffering is a
 * "definition," not a "workflow." It sits between two independently-
 * lifecycled entities (AcademicYear, Term); a boolean here would create
 * an authority conflict with no clear resolution (e.g. AcademicYear =
 * Closed but SubjectOffering = Active -- which wins?). Its validity is
 * derived entirely from what it references (Term/TeacherAssignment/
 * Timetable), never stated independently. If a real need for an
 * explicit lifecycle ever surfaces, it should be a full state machine,
 * not a boolean retrofit (the user's own stated fallback).
 *
 * Three-way Consistency Invariant (enforced in booted()::saving(), same
 * mechanism as SectionAssignment's own): Section.academic_year_id,
 * Term.academic_year_id, and this row's own stored academic_year_id
 * must all agree. academic_year_id is stored directly rather than
 * derived via term.academic_year_id (BUS-0032's required amendment) --
 * nearly all real queries/reports filter by year directly.
 *
 * A 4th Consistency Invariant is intentionally documented but NOT
 * enforced here: Section.grade_level must support this Subject (i.e.
 * the Subject must be part of the curriculum for that grade level).
 * Enforcement is deferred until Curriculum Path/Specification models
 * exist (Addendum B1, "promotion not prediction") -- there is nothing
 * to check against yet. A future migration should add this check to
 * this same booted()::saving() hook, not a new mechanism.
 *
 * Room and Timetable are explicitly OUT OF SCOPE for Academic -- the
 * user's own binding instruction ("لا أسمح بإنشائه داخل Academic," i.e.
 * Room may not be created inside Academic) requires a dedicated,
 * independent ADR resolving Room's ownership (it will also be consumed
 * by Exams, Labs, Booking, Events, and Smart Campus) before any
 * Timetable code is written. This model carries no room/schedule
 * columns and no relation toward either concept.
 */
class SubjectOffering extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = [
        'subject_id', 'section_id', 'term_id', 'academic_year_id',
    ];

    protected static function newFactory(): SubjectOfferingFactory
    {
        return SubjectOfferingFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $subjectOffering): void {
            $section = Section::findOrFail($subjectOffering->section_id);
            $term = Term::findOrFail($subjectOffering->term_id);

            if ($section->academic_year_id !== $subjectOffering->academic_year_id) {
                throw new TermAcademicYearMismatchException(
                    'Section',
                    $subjectOffering->academic_year_id,
                    $section->academic_year_id,
                );
            }

            if ($term->academic_year_id !== $subjectOffering->academic_year_id) {
                throw new TermAcademicYearMismatchException(
                    'Term',
                    $subjectOffering->academic_year_id,
                    $term->academic_year_id,
                );
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subject_id', 'section_id', 'term_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
