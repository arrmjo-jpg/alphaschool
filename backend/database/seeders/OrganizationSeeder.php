<?php

namespace Database\Seeders;

use App\Core\Models\Organization;
use App\Modules\Identity\Models\School;
use Illuminate\Database\Seeder;

/**
 * Exactly one Organization and one School row (docs/DOMAIN_BLUEPRINT.md
 * Addendum A2/B) -- the dedicated-instance model (ADR-0006) means there
 * is only ever one customer per deployment, so this seeder is
 * deliberately not designed to create more than one of each.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['legal_name' => 'AlphaSchool'],
            ['display_name' => 'AlphaSchool'],
        );

        School::firstOrCreate(
            ['organization_id' => $organization->id],
            ['name_en' => 'AlphaSchool', 'name_ar' => 'ألفا سكول'],
        );
    }
}
