<?php

namespace Database\Factories;

use App\Modules\Admissions\Models\AdmissionAssessment;
use App\Modules\Admissions\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionAssessment>
 */
class AdmissionAssessmentFactory extends Factory
{
    protected $model = AdmissionAssessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'scheduled_at' => now(),
            'completed_at' => null,
            'score' => null,
            'notes' => null,
        ];
    }

    public function completed(float $score): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'score' => $score,
        ]);
    }
}
