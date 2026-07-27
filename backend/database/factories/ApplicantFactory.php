<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'submitted_by_guardian_id' => Guardian::factory(),
            'branch_id' => Branch::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'applied_for_grade_level_id' => GradeLevel::factory(),
            'application_number' => 'APP-'.fake()->unique()->numberBetween(100000, 999999),
            'status' => Applicant::STATUS_SUBMITTED,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Applicant::STATUS_UNDER_REVIEW]);
    }

    public function tested(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Applicant::STATUS_TESTED]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Applicant::STATUS_ACCEPTED]);
    }
}
