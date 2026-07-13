<?php

namespace Database\Factories;

use App\Core\Services\DuplicateDetectionService;
use App\Modules\IdentityMaintenance\Models\DuplicateFlag;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuplicateFlag>
 */
class DuplicateFlagFactory extends Factory
{
    protected $model = DuplicateFlag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_person_id' => Person::factory(),
            'candidate_person_id' => Person::factory(),
            'score' => 65,
            'tier' => DuplicateDetectionService::TIER_LIKELY,
            'status' => DuplicateFlag::STATUS_PENDING,
            'resolved_by_id' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }

    public function certain(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => 90,
            'tier' => DuplicateDetectionService::TIER_CERTAIN,
        ]);
    }
}
