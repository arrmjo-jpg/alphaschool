<?php

namespace Database\Seeders;

use App\Core\Models\Organization;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\School;
use Illuminate\Database\Seeder;

/**
 * Exactly one Organization, one School, and one Branch row
 * (docs/DOMAIN_BLUEPRINT.md Addendum A2/B) -- the dedicated-instance
 * model (ADR-0006) means there is only ever one customer per
 * deployment, so this seeder is deliberately not designed to create
 * more than one of each.
 *
 * The Branch specifically exists because `model_has_permissions` and
 * `model_has_roles` both have a NOT NULL `branch_id` (Spatie's Teams
 * feature) -- without at least one real Branch row, no branch-scoped
 * permission grant is possible in a fresh environment, even though the
 * seeded Super Admin doesn't itself need one (its bypass is the
 * `is_super_admin` flag, not a permission grant).
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['legal_name' => 'AlphaSchool'],
            ['display_name' => 'AlphaSchool'],
        );

        $school = School::firstOrCreate(
            ['organization_id' => $organization->id],
            ['name_en' => 'AlphaSchool', 'name_ar' => 'ألفا سكول'],
        );

        Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['school_id' => $school->id, 'name_en' => 'Main Branch', 'name_ar' => 'الفرع الرئيسي'],
        );
    }
}
