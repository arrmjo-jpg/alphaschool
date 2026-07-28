<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'grade_level_id' => GradeLevel::factory(),
            // Not ->unique() -- the real constraint is composite
            // (branch_id, academic_year_id, grade_level_id, name), and
            // each factory call gets its own independent Branch/
            // AcademicYear/GradeLevel by default, so a global unique()
            // pool would exhaust its 26 letters needlessly across a
            // full test run for no real protection.
            'name' => fake()->randomLetter(),
            'capacity' => fake()->numberBetween(20, 30),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
