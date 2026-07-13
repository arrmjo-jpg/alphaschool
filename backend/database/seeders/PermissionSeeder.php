<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeder-driven permission definitions (docs/DOMAIN_BLUEPRINT.md §8) --
 * permissions and their groups are never admin-UI-creatable. Roles are
 * globally defined (Spatie's teams feature would otherwise auto-scope a
 * new Role to whatever team context happens to be active when it's
 * created -- every Role here is created with an explicit `branch_id =
 * null` to guarantee that, regardless of ambient context); only
 * *assignment* (model_has_roles) is branch-scoped.
 */
class PermissionSeeder extends Seeder
{
    private const GUARD = 'sanctum';

    public function run(): void
    {
        $groups = $this->seedPermissionGroups();
        $permissions = $this->seedPermissions($groups);
        $this->seedRoles($permissions);
    }

    /**
     * @return array<string, PermissionGroup>
     */
    private function seedPermissionGroups(): array
    {
        $definitions = [
            'identity' => ['sort_order' => 1, 'icon' => 'shield', 'name' => ['en' => 'Identity & Access', 'ar' => 'الهوية والصلاحيات']],
            'people' => ['sort_order' => 2, 'icon' => 'users', 'name' => ['en' => 'People', 'ar' => 'الأشخاص']],
            // Deliberately separate from 'identity' -- Addendum C10: "not
            // generic admin access". Merge/Anonymization approval and
            // Duplicate Resolution review sit behind their own dedicated
            // group, never folded into ordinary Identity & Access grants.
            'identity-governance' => ['sort_order' => 3, 'icon' => 'shield-check', 'name' => ['en' => 'Identity Governance', 'ar' => 'حوكمة الهوية']],
        ];

        $groups = [];

        foreach ($definitions as $code => $attributes) {
            $groups[$code] = PermissionGroup::firstOrCreate(
                ['code' => $code],
                ['name' => $attributes['name'], 'sort_order' => $attributes['sort_order'], 'icon' => $attributes['icon']],
            );
        }

        return $groups;
    }

    /**
     * @param  array<string, PermissionGroup>  $groups
     * @return array<string, Permission>
     */
    private function seedPermissions(array $groups): array
    {
        // {resource}.{action} -- docs/developer/authorization.md. Only
        // resources that actually exist today (Identity's own Branches/
        // Roles, People's Person) get permissions; students.*,
        // invoices.*, etc. are seeded by their own module's own sprint,
        // not fabricated here ahead of the tables they'd protect.
        $definitions = [
            'branches.view' => ['group' => 'identity', 'name' => ['en' => 'View Branches', 'ar' => 'عرض الفروع']],
            'branches.create' => ['group' => 'identity', 'name' => ['en' => 'Create Branches', 'ar' => 'إنشاء الفروع']],
            'branches.update' => ['group' => 'identity', 'name' => ['en' => 'Update Branches', 'ar' => 'تعديل الفروع']],
            'roles.view' => ['group' => 'identity', 'name' => ['en' => 'View Roles', 'ar' => 'عرض الأدوار']],
            'roles.create' => ['group' => 'identity', 'name' => ['en' => 'Create Roles', 'ar' => 'إنشاء الأدوار']],
            'roles.update' => ['group' => 'identity', 'name' => ['en' => 'Update Roles', 'ar' => 'تعديل الأدوار']],
            'people.view' => ['group' => 'people', 'name' => ['en' => 'View People', 'ar' => 'عرض الأشخاص']],
            'people.create' => ['group' => 'people', 'name' => ['en' => 'Create People', 'ar' => 'إنشاء الأشخاص']],
            'people.update' => ['group' => 'people', 'name' => ['en' => 'Update People', 'ar' => 'تعديل الأشخاص']],
            // Addendum C10 -- named explicitly in the Blueprint text.
            // identity.review-duplicates is enforced this sprint
            // (DuplicateResolutionService); approve-merge/
            // approve-anonymization are seeded now as vocabulary only --
            // real enforcement arrives with Sprint 3.2/3.3, and which
            // role(s) should hold that authority is a business decision,
            // not one this seeder makes on the business's behalf.
            'identity.review-duplicates' => ['group' => 'identity-governance', 'name' => ['en' => 'Review Duplicate Persons', 'ar' => 'مراجعة الأشخاص المكررين']],
            'identity.approve-merge' => ['group' => 'identity-governance', 'name' => ['en' => 'Approve Person Merge', 'ar' => 'اعتماد دمج الأشخاص']],
            'identity.approve-anonymization' => ['group' => 'identity-governance', 'name' => ['en' => 'Approve Person Anonymization', 'ar' => 'اعتماد إخفاء هوية الشخص']],
        ];

        $permissions = [];

        foreach ($definitions as $name => $attributes) {
            $permissions[$name] = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['permission_group_id' => $groups[$attributes['group']]->id, 'display_name' => $attributes['name']],
            );
        }

        return $permissions;
    }

    /**
     * @param  array<string, Permission>  $permissions
     */
    private function seedRoles(array $permissions): void
    {
        // Principal, Teacher, Registrar, HR Manager, Accountant -- the
        // baseline job titles named in the original Users-module design
        // session. Only roles whose responsibilities map to something
        // that exists today (Identity, People) get real permissions;
        // Teacher/HR Manager/Accountant are seeded as empty role shells
        // since Academic/HR/Finance don't exist yet (Phases 5-7) --
        // granting permissions for resources that don't exist would be
        // fabricating access to nothing. Each future module attaches
        // its own real permissions to these roles as it ships.
        $roleDefinitions = [
            'principal' => [
                'display_name' => ['en' => 'Principal', 'ar' => 'المدير'],
                'permissions' => ['branches.view', 'people.view'],
            ],
            'registrar' => [
                'display_name' => ['en' => 'Registrar', 'ar' => 'أمين السجلات'],
                // identity.review-duplicates specifically, not approve-
                // merge/approve-anonymization -- reviewing a flagged
                // candidate pair is ordinary registrar work; approving an
                // actual Merge/Anonymization is the higher-stakes,
                // four-eyes authority C10 deliberately keeps separate.
                'permissions' => ['people.view', 'people.create', 'people.update', 'identity.review-duplicates'],
            ],
            'teacher' => [
                'display_name' => ['en' => 'Teacher', 'ar' => 'معلم'],
                'permissions' => [],
            ],
            'hr_manager' => [
                'display_name' => ['en' => 'HR Manager', 'ar' => 'مدير الموارد البشرية'],
                'permissions' => [],
            ],
            'accountant' => [
                'display_name' => ['en' => 'Accountant', 'ar' => 'محاسب'],
                'permissions' => [],
            ],
        ];

        foreach ($roleDefinitions as $name => $definition) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD, 'branch_id' => null],
                ['display_name' => $definition['display_name']],
            );

            $role->syncPermissions(array_map(
                fn (string $permissionName) => $permissions[$permissionName],
                $definition['permissions'],
            ));
        }
    }
}
