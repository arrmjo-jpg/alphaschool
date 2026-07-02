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
        // Example of the shape a future module's seeding call will take,
        // left commented out deliberately -- no real contexts exist yet:
        //
        // ReasonCode::updateOrCreate(
        //     ['context' => 'employment', 'code' => 'retirement'],
        //     ['label' => ['en' => 'Retirement', 'ar' => 'التقاعد'], 'is_active' => true],
        // );
    }
}
