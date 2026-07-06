<?php

namespace App\Modules\Identity\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The immediate parent in a future Org -> School -> Branch hierarchy
 * (a real possible future shape -- the same Organization eventually
 * owning multiple distinctly-branded schools) -- but deliberately
 * shallow this sprint: one School row, seeded under the one Organization
 * row. School-level Settings overrides, School-level Team/permission
 * scoping, and School-level branding are real, unanswered design
 * questions, not built here.
 *
 * Lives in Identity, not Core -- unlike Organization (vendor/licensing
 * identity, generic to any B2B product), "School" is a domain/academic
 * concept specific to running a school, and its only current purpose is
 * sitting above Branch, Identity's Teams-scoping unit.
 */
class School extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'name_en', 'name_ar'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
