<?php

namespace Database\Factories;

use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'lifecycle_status' => Employee::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['lifecycle_status' => Employee::STATUS_INACTIVE]);
    }
}
