<?php

namespace Database\Factories;

use App\Core\Models\ReasonCode;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\SuspensionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuspensionRecord>
 */
class SuspensionRecordFactory extends Factory
{
    protected $model = SuspensionRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            // ReasonCode has no HasFactory/factory of its own (Sprint
            // 4.2 hit the same gap) -- created directly here instead.
            'reason_code_id' => fn () => ReasonCode::create([
                'context' => 'enrollment_suspension',
                'code' => 'disciplinary_action',
                'label' => ['en' => 'Disciplinary action', 'ar' => 'إجراء تأديبي'],
                'is_active' => true,
            ])->id,
            'suspended_at' => now(),
            'reinstated_at' => null,
        ];
    }
}
