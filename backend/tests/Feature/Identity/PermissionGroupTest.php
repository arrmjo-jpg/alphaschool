<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;

it('renders permission group name and description in both Arabic and English', function () {
    $group = PermissionGroup::create([
        'code' => 'students',
        'name' => ['en' => 'Students', 'ar' => 'الطلاب'],
        'description' => ['en' => 'Student records management', 'ar' => 'إدارة سجلات الطلاب'],
        'sort_order' => 1,
    ]);

    expect($group->getTranslation('name', 'en'))->toBe('Students')
        ->and($group->getTranslation('name', 'ar'))->toBe('الطلاب')
        ->and($group->getTranslation('description', 'en'))->toBe('Student records management')
        ->and($group->getTranslation('description', 'ar'))->toBe('إدارة سجلات الطلاب');
});

it('associates a permission with its group via an explicit FK, not a parsed code prefix', function () {
    $group = PermissionGroup::create(['code' => 'students', 'name' => ['en' => 'Students', 'ar' => 'الطلاب']]);

    $permission = Permission::create([
        'name' => 'students.view',
        'guard_name' => 'sanctum',
        'permission_group_id' => $group->id,
        'display_name' => ['en' => 'View Students', 'ar' => 'عرض الطلاب'],
    ]);

    expect($permission->group->id)->toBe($group->id)
        ->and($group->permissions()->count())->toBe(1);
});

it('gives Role a translatable display_name/description while keeping name immutable-by-convention', function () {
    $role = Role::create([
        'name' => 'teacher',
        'guard_name' => 'sanctum',
        'display_name' => ['en' => 'Teacher', 'ar' => 'معلم'],
    ]);

    expect($role->name)->toBe('teacher')
        ->and($role->getTranslation('display_name', 'en'))->toBe('Teacher')
        ->and($role->getTranslation('display_name', 'ar'))->toBe('معلم')
        ->and($role->is_active)->toBeTrue();
});

it('deactivates a role rather than deleting it, leaving historical grants intact', function () {
    $role = Role::create(['name' => 'teacher', 'guard_name' => 'sanctum']);

    $role->update(['is_active' => false]);

    expect(Role::find($role->id))->not->toBeNull()
        ->and($role->fresh()->is_active)->toBeFalse();
});
