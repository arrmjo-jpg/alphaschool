<?php

namespace Database\Factories;

use App\Modules\Academic\Models\BranchGradeLevel;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchGradeLevel>
 */
class BranchGradeLevelFactory extends Factory
{
    protected $model = BranchGradeLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'is_active' => true,
        ];
    }
}
