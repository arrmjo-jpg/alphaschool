<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Translatable grouping/display metadata for Permissions
 * (docs/DOMAIN_BLUEPRINT.md §8) -- system vocabulary (Addendum B5:
 * genuinely means the same thing in both languages), so Spatie
 * Translatable applies here, unlike Person's names.
 *
 * `code` is the stable identifier the Admin Workspace registry maps to
 * (docs/ADMIN_PLATFORM.md: "A Workspace is the presentation-layer
 * counterpart to the already-decided Permission Groups") -- treated as
 * immutable once referenced, the same convention as Permission's own
 * `name`.
 */
class PermissionGroup extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = ['code', 'name', 'description', 'sort_order', 'icon'];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
