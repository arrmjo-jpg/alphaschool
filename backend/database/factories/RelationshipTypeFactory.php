<?php

namespace Database\Factories;

use App\Modules\People\Models\RelationshipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelationshipType>
 */
class RelationshipTypeFactory extends Factory
{
    protected $model = RelationshipType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'name' => ['en' => $this->faker->word(), 'ar' => $this->faker->word()],
            'scope' => RelationshipType::SCOPE_PERSON_RELATIONSHIP,
            'is_active' => true,
        ];
    }

    public function guardianStudent(): static
    {
        return $this->state(fn (array $attributes) => ['scope' => RelationshipType::SCOPE_GUARDIAN_STUDENT]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
