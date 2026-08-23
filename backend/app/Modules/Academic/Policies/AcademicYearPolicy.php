<?php

namespace App\Modules\Academic\Policies;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Identity\Models\User;

/**
 * Governs who may manage the catalog (AcademicYear, GradeLevel,
 * BranchGradeLevel all share this one policy -- same actors, same
 * action shape, per MergeRequestPolicy's own precedent of one policy
 * class per meaningfully-distinct action set, not strictly one per
 * model). Uses hasPermissionTo($permission, 'sanctum') explicitly, not
 * can() -- Sprint 3.1 found this app's default auth guard ('web')
 * silently breaks can()'s permission resolution, since every permission
 * here is seeded under 'sanctum'.
 *
 * Which named role actually holds academic.manage-catalog isn't decided
 * yet -- Academic's own certified Reference Blueprint doesn't name a
 * persona for catalog management (Sprint 4.1 Technical Specification,
 * "Open item"). The permission is seeded but deliberately not assigned
 * to any role shell, matching PermissionSeeder's own precedent for
 * not-yet-decided ownership.
 */
class AcademicYearPolicy
{
    private const PERMISSION = 'academic.manage-catalog';

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(self::PERMISSION, 'sanctum');
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->hasPermissionTo(self::PERMISSION, 'sanctum');
    }

    public function close(User $user, AcademicYear $academicYear): bool
    {
        return $user->hasPermissionTo(self::PERMISSION, 'sanctum');
    }
}
