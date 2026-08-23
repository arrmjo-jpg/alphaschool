<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ->unique() -- name_en now carries a DB unique constraint
        // (Independent Code Review fix); a non-unique fake() call here
        // would make any test creating more than a handful of
        // AcademicYear rows flaky against real collisions.
        $startYear = fake()->unique()->numberBetween(2024, 2030);

        return [
            'name_en' => "{$startYear}-".($startYear + 1),
            'name_ar' => "{$startYear}-".($startYear + 1),
            'start_date' => "{$startYear}-09-01",
            'end_date' => ($startYear + 1).'-06-30',
            'status' => AcademicYear::STATUS_UPCOMING,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AcademicYear::STATUS_ACTIVE]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AcademicYear::STATUS_CLOSED]);
    }
}
