<?php

namespace Database\Factories;

use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonRelationship;
use App\Modules\People\Models\RelationshipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonRelationship>
 */
class PersonRelationshipFactory extends Factory
{
    protected $model = PersonRelationship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'related_person_id' => Person::factory(),
            'relationship_type_id' => RelationshipType::factory(), // defaults to person_relationship scope
        ];
    }
}
