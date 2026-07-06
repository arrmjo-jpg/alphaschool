<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(6)),
            'name_en' => fake()->unique()->company(),
            'name_ar' => 'فرع '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
