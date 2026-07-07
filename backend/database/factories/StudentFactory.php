<?php

namespace Database\Factories;

use App\Modules\People\Models\Person;
use App\Modules\People\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'lifecycle_status' => Student::STATUS_ACTIVE,
        ];
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => ['lifecycle_status' => Student::STATUS_GRADUATED]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['lifecycle_status' => Student::STATUS_WITHDRAWN]);
    }
}
