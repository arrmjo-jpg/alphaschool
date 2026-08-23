<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\Term;
use App\Modules\Academic\Services\AcademicCatalogService;
use Illuminate\Support\Facades\DB;

/**
 * No row-locking here, unlike AssignSectionAction/AssignHomeroomTeacherAction
 * -- both of those lock their natural anchor row to serialize
 * HasTemporalAssignment's guardAgainstOverlap() race. SubjectOffering
 * uses no temporal assignment/overlap semantics at all; its uniqueness
 * (subject_id, section_id, term_id) is a plain DB unique index, which
 * already resolves a concurrent double-insert atomically without an
 * application-level lock. This Action exists only to route the closed-
 * Term check through AcademicCatalogService, matching this codebase's
 * own convention that AcademicCatalogService is always consumed by an
 * Action, never called directly from inside a model.
 */
class CreateSubjectOfferingAction
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function execute(Subject $subject, Section $section, Term $term): SubjectOffering
    {
        return DB::transaction(function () use ($subject, $section, $term): SubjectOffering {
            $this->academicCatalogService->assertTermIsOpen($term->id);

            // SubjectOffering's own three-way Consistency Invariant
            // (booted()::saving()) throws if $section/$term/the stored
            // academic_year_id don't all agree -- not re-checked here.
            return SubjectOffering::create([
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'term_id' => $term->id,
                'academic_year_id' => $section->academic_year_id,
            ]);
        });
    }
}
