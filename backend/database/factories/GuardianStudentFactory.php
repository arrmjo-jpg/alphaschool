<?php

namespace Database\Factories;

use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\RelationshipType;
use App\Modules\People\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianStudent>
 */
class GuardianStudentFactory extends Factory
{
    protected $model = GuardianStudent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_id' => Guardian::factory(),
            'student_id' => Student::factory(),
            'relationship_type_id' => RelationshipType::factory()->guardianStudent(),
            'is_primary_contact' => false,
            'is_pickup_authorized' => false,
            'custody_restriction_notes' => null,
            'verified_by_id' => null,
            'verified_at' => null,
            'effective_from' => now()->startOfDay(),
            'effective_until' => null,
            'status' => 'active',
        ];
    }

    public function primaryContact(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary_contact' => true]);
    }
}
