<?php

namespace Database\Factories;

use App\Modules\People\Models\BillingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingGroup>
 */
class BillingGroupFactory extends Factory
{
    protected $model = BillingGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_en' => $this->faker->lastName().' Siblings',
            'name_ar' => 'أشقاء '.$this->faker->lastName(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
