<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\MergeRequest;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MergeRequest>
 */
class MergeRequestFactory extends Factory
{
    protected $model = MergeRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'losing_person_id' => Person::factory(),
            'winning_person_id' => Person::factory(),
            'duplicate_flag_id' => null,
            'status' => MergeRequest::STATUS_DRAFT,
            'requested_by_id' => User::factory(),
        ];
    }
}
