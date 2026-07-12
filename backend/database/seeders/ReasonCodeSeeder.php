<?php

namespace Database\Seeders;

use App\Core\Models\ReasonCode;
use Illuminate\Database\Seeder;

/**
 * Scaffold only (Sprint 1.1) -- Core owns the reason_codes table shape,
 * not its contents. Each future module seeds its own context's reasons
 * here as that module is actually built (e.g. Academic adds 'enrollment'
 * reasons in Phase 4/5, HR adds 'employment' reasons in Phase 6) --
 * fabricating reason codes for contexts that don't exist yet would be
 * exactly the "prediction, not promotion" mistake this project has
 * consistently avoided elsewhere (docs/DOMAIN_BLUEPRINT.md Addendum B1).
 */
class ReasonCodeSeeder extends Seeder
{
    public function run(): void
    {
        // People/Family (Sprint 2.5) -- guardian_student is
        // HasTemporalAssignment's first real production consumer, so
        // this context's reasons are seeded now, not speculatively:
        // real ways a guardian_student relationship legitimately ends
        // (closeAssignment) versus was never valid to begin with
        // (cancelAssignment).
        $guardianStudentReasons = [
            ['code' => 'custody_change', 'label' => ['en' => 'Custody arrangement changed', 'ar' => 'تغيّر ترتيب الحضانة']],
            ['code' => 'student_withdrawn', 'label' => ['en' => 'Student withdrawn', 'ar' => 'انسحاب الطالب']],
            ['code' => 'entered_in_error', 'label' => ['en' => 'Entered in error', 'ar' => 'تم إدخالها بالخطأ']],
        ];

        foreach ($guardianStudentReasons as $reason) {
            ReasonCode::updateOrCreate(
                ['context' => 'guardian_student_relationship', 'code' => $reason['code']],
                ['label' => $reason['label'], 'is_active' => true],
            );
        }
    }
}
