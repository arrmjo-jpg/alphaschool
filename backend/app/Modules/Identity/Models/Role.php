<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Translatable\HasTranslations;

/**
 * Extends Spatie's base Role (docs/DOMAIN_BLUEPRINT.md §8): globally
 * defined, Employee-only, branch-scoped only in *assignment* via Spatie
 * Teams -- Role itself is never branch-specific.
 *
 * `name` (Spatie's own column) stays the plain immutable English code
 * used by hasRole()/can() checks; `display_name`/`description` are
 * Translatable (system vocabulary, Addendum B5).
 *
 * Deactivated via `is_active`, never SoftDeletes -- see the migration
 * for why (a "deleted" Role must not leave model_has_roles rows
 * pointing at a semantically-gone parent).
 *
 * Deliberately NO role inheritance/hierarchy: explicit per-role
 * permission sets, even with some duplication, are more maintainable
 * across staff turnover than an implicit inheritance chain (agreed
 * before implementation) -- this class must never grow a "parent role"
 * concept.
 */
class Role extends SpatieRole
{
    use HasFactory;
    use HasTranslations;

    public array $translatable = ['display_name', 'description'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
