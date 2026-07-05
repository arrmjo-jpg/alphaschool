<?php

namespace Database\Factories;

use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name_en' => fake()->firstName(),
            'family_name_en' => fake()->lastName(),
            'first_name_ar' => 'أحمد',
            'family_name_ar' => 'الرشيد',
            'dob' => fake()->date(),
            'gender' => fake()->randomElement([Person::GENDER_MALE, Person::GENDER_FEMALE]),
            'nationality' => 'JO',
        ];
    }
}
