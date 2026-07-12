<?php

namespace Database\Seeders;

use App\Modules\People\Models\RelationshipType;
use Illuminate\Database\Seeder;

/**
 * Seeder-driven vocabulary (docs/DOMAIN_BLUEPRINT.md §11) -- not
 * admin-UI-creatable in this sprint. Deliberately seeds the paternal/
 * maternal kinship terms as genuinely separate rows (عم vs خال, جد لأب
 * vs جد لأم) -- the concrete case that justified the lookup-table
 * decision over a PHP enum; seeding these collapsed into one shared
 * English-only label would silently regress that decision.
 */
class RelationshipTypeSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            // guardian_student scope -- the relationship role a Guardian
            // holds toward a specific Student.
            ['code' => 'father', 'scope' => RelationshipType::SCOPE_GUARDIAN_STUDENT, 'name' => ['en' => 'Father', 'ar' => 'الأب']],
            ['code' => 'mother', 'scope' => RelationshipType::SCOPE_GUARDIAN_STUDENT, 'name' => ['en' => 'Mother', 'ar' => 'الأم']],
            ['code' => 'legal_guardian', 'scope' => RelationshipType::SCOPE_GUARDIAN_STUDENT, 'name' => ['en' => 'Legal Guardian', 'ar' => 'ولي أمر قانوني']],
            ['code' => 'court_appointed_guardian', 'scope' => RelationshipType::SCOPE_GUARDIAN_STUDENT, 'name' => ['en' => 'Court-Appointed Guardian', 'ar' => 'ولي أمر معيّن قضائيًا']],

            // person_relationship scope -- informational kinship at the
            // Person level, independent of any Guardian/Student role.
            ['code' => 'sibling', 'scope' => RelationshipType::SCOPE_PERSON_RELATIONSHIP, 'name' => ['en' => 'Sibling', 'ar' => 'شقيق/شقيقة']],
            ['code' => 'uncle_paternal', 'scope' => RelationshipType::SCOPE_PERSON_RELATIONSHIP, 'name' => ['en' => 'Uncle (paternal)', 'ar' => 'عم']],
            ['code' => 'uncle_maternal', 'scope' => RelationshipType::SCOPE_PERSON_RELATIONSHIP, 'name' => ['en' => 'Uncle (maternal)', 'ar' => 'خال']],
            ['code' => 'grandfather_paternal', 'scope' => RelationshipType::SCOPE_PERSON_RELATIONSHIP, 'name' => ['en' => 'Grandfather (paternal)', 'ar' => 'جد لأب']],
            ['code' => 'grandfather_maternal', 'scope' => RelationshipType::SCOPE_PERSON_RELATIONSHIP, 'name' => ['en' => 'Grandfather (maternal)', 'ar' => 'جد لأم']],
        ];

        foreach ($definitions as $definition) {
            RelationshipType::firstOrCreate(
                ['code' => $definition['code']],
                ['scope' => $definition['scope'], 'name' => $definition['name'], 'is_active' => true],
            );
        }
    }
}
