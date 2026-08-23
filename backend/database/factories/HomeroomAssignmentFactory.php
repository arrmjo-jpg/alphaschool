<?php

namespace Database\Factories;

use App\Modules\Academic\Models\HomeroomAssignment;
use App\Modules\Academic\Models\Section;
use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeroomAssignment>
 */
class HomeroomAssignmentFactory extends Factory
{
    protected $model = HomeroomAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'section_id' => Section::factory(),
            'effective_from' => now(),
            'effective_until' => null,
            'status' => 'active',
        ];
    }
}
