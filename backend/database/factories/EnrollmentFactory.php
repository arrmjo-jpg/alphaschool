<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Identity\Models\Branch;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'branch_id' => Branch::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'status' => Enrollment::STATUS_ACTIVE,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Enrollment::STATUS_WITHDRAWN]);
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Enrollment::STATUS_GRADUATED]);
    }
}
