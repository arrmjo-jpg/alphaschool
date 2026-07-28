<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectOffering>
 */
class SubjectOfferingFactory extends Factory
{
    protected $model = SubjectOffering::class;

    /**
     * Default definition builds a consistent triple sharing a single
     * AcademicYear -- Section and Term each get their own factory-created
     * AcademicYear by default, which would fail the three-way Consistency
     * Invariant. A test that needs an intentionally-mismatched triple
     * should override section_id/term_id/academic_year_id explicitly to
     * exercise TermAcademicYearMismatchException, not rely on this
     * factory's default happening to collide.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $academicYear = AcademicYear::factory()->create();

        return [
            'subject_id' => Subject::factory(),
            'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id]),
            'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id]),
            'academic_year_id' => $academicYear->id,
        ];
    }
}
