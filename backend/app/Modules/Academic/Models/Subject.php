<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The global subject catalog (Subject Offering + Timetables Architecture
 * Pass), reusable across every branch/year/term -- the same permanent-
 * catalog category as GradeLevel, not a per-year entity like Section/
 * Term. Deliberately minimal scope, disclosed explicitly: code/name_en/
 * name_ar/is_active only. BUS-0018's own full formula
 * (Subject x Term x Section x Teacher x Room x Schedule) implies a
 * richer Subject eventually -- versioning, equivalency, prerequisites,
 * department -- none of which exist here because nothing consumes them
 * yet (Addendum B1, "promotion not prediction").
 *
 * Curriculum's own future "which grade levels teach this Subject"
 * invariant is intentionally NOT enforced here (see SubjectOffering's
 * own docblock for the deferred 4th Consistency Invariant) -- Subject
 * itself carries no grade-level scoping today.
 */
class Subject extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    protected $fillable = [
        'code', 'name_en', 'name_ar', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): SubjectFactory
    {
        return SubjectFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name_en', 'name_ar', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
