<?php

namespace App\Modules\Identity\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use RuntimeException;

/**
 * Owns only its own facts (docs/DOMAIN_BLUEPRINT.md §3: "active status,
 * settings-override scope, optional parent branch. Other aggregates
 * reference it; it never owns them.") -- and, this sprint, the Spatie
 * Teams scoping unit for every Role assignment
 * (config/permission.php's team_foreign_key = branch_id).
 *
 * Deactivated via `is_active`, never SoftDeletes -- a "deleted" Branch
 * must never leave model_has_roles rows, or any future branch_id FK,
 * pointing at a semantically-gone parent. This is a reference/
 * structural entity (stop it from being newly assignable), not a
 * recoverable record the way Person/User/Media are.
 */
class Branch extends Model
{
    use HasFactory;
    use HasPublicId;

    private const CODE_PATTERN = '/^[A-Z0-9]{2,10}$/';

    protected $fillable = ['school_id', 'parent_branch_id', 'code', 'name_en', 'name_ar', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $branch): void {
            if ($branch->exists && $branch->isDirty('code')) {
                throw new RuntimeException(
                    'Branch: code is immutable once set -- used by reports, integrations, accounting, and inventory.'
                );
            }

            if (! preg_match(self::CODE_PATTERN, (string) $branch->code)) {
                throw new InvalidArgumentException(
                    "Branch: '{$branch->code}' is not a structurally valid code (expected 2-10 uppercase letters/digits)."
                );
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parentBranch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_branch_id');
    }

    public function childBranches(): HasMany
    {
        return $this->hasMany(self::class, 'parent_branch_id');
    }
}
