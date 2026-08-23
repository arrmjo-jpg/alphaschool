<?php

namespace Database\Factories;

use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    protected $model = TeacherAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'subject_offering_id' => SubjectOffering::factory(),
            'effective_from' => now(),
            'effective_until' => null,
            'status' => 'active',
        ];
    }
}
