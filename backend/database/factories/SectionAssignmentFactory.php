<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SectionAssignment;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionAssignment>
 */
class SectionAssignmentFactory extends Factory
{
    protected $model = SectionAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Deliberately not two independent Enrollment::factory()/
        // Section::factory() calls -- each would create its own
        // unrelated Branch/AcademicYear/GradeLevel, guaranteed to fail
        // SectionAssignment's own Consistency Invariant. Both are built
        // from the same shared triple here so the factory's default
        // state is valid out of the box; a test exercising the
        // invariant itself overrides enrollment_id/section_id
        // explicitly with a genuinely mismatched pair.
        $branch = Branch::factory()->create();
        $academicYear = AcademicYear::factory()->active()->create();
        $gradeLevel = GradeLevel::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);

        $section = Section::factory()->create([
            'branch_id' => $branch->id,
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);

        return [
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'effective_from' => now(),
            'effective_until' => null,
            'status' => 'active',
        ];
    }
}
