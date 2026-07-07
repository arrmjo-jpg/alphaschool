<?php

namespace Database\Factories;

use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'lifecycle_status' => Guardian::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['lifecycle_status' => Guardian::STATUS_INACTIVE]);
    }
}
