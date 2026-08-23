<?php

namespace Database\Factories;

use App\Modules\Academic\Models\GradeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeLevel>
 */
class GradeLevelFactory extends Factory
{
    protected $model = GradeLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ->unique() per attribute, not a shared Sequence -- a Sequence
        // instantiated inline here would reset to its first value on
        // every call, producing duplicate sequence_order values (and a
        // constraint violation) the moment a test creates more than one
        // GradeLevel.
        $number = fake()->unique()->numberBetween(1, 200);

        return [
            'code' => "G{$number}",
            'name_en' => "Grade {$number}",
            'name_ar' => 'الصف',
            'sequence_order' => $number,
            'is_active' => true,
        ];
    }
}
