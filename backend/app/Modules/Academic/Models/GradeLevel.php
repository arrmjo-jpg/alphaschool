<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Modules\Identity\Models\Branch;
use Database\Factories\GradeLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The global grade-level catalog (KG1..Grade 12), reusable across every
 * branch (Sprint 4.1 Technical Specification). Which branches actually
 * offer a given level is BranchGradeLevel's own concern, not this
 * model's.
 */
class GradeLevel extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = [
        'code', 'name_en', 'name_ar', 'sequence_order', 'is_active',
    ];

    /** Mirrors AcademicYear's own identical fix (UI Sprint 1-B, docs/ADMIN_DESIGN_SYSTEM.md §28.17) -- the migration's `default(true)` is DB-level only, never reflected into a freshly ::create()'d instance without this. */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): GradeLevelFactory
    {
        return GradeLevelFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_grade_levels')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name_en', 'name_ar', 'sequence_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
