<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Translatable\HasTranslations;

/**
 * Extends Spatie's base Permission (docs/DOMAIN_BLUEPRINT.md §8):
 * code/seeder-defined, never admin-UI-creatable, never granted directly
 * to a user -- always through a Role.
 *
 * `name` (Spatie's own column) stays the plain immutable code used by
 * can() checks, following the {resource}.{action} convention (e.g.
 * `students.view`) -- see docs/developer/authorization.md.
 * `permission_group_id` is an explicit FK, never parsed from `name`,
 * since a permission's display grouping doesn't always match its code
 * prefix.
 */
class Permission extends SpatiePermission
{
    use HasFactory;
    use HasTranslations;

    public array $translatable = ['display_name', 'description'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }
}
