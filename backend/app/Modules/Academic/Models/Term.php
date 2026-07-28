<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Academic's own Master Data (BUS-0032, "Term Introduced as Master
 * Data"), resolved as a real entity -- not a synonym for AcademicYear
 * (this ADR's own first-draft conclusion, reversed once a confirmed
 * near-term commercial need for 2-3 terms/year surfaced) and not a
 * property of SubjectOffering. A child of exactly one AcademicYear,
 * per-year like Section, mirroring AcademicYear's own 3-state lifecycle
 * exactly (upcoming/active/closed) so a Term can be closed independently
 * of its year (e.g. First Term closes while the AcademicYear itself
 * stays active into Second Term).
 *
 * No TermClosed domain event -- unlike AcademicYearClosed, nothing
 * consumes a Term-scoped cache or reacts to a Term closing yet (Addendum
 * B1, "promotion not prediction"). Add one the same way Sprint A0 added
 * row-locking to HasTemporalAssignment: when a second real consumer
 * actually needs it, not speculatively here.
 */
class Term extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'academic_year_id', 'name_en', 'name_ar', 'sequence_order', 'start_date', 'end_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function newFactory(): TermFactory
    {
        return TermFactory::new();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Idempotent, matching AcademicYear::close() exactly -- closing an
     * already-closed Term is a no-op, not an exception.
     */
    public function close(): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->status = self::STATUS_CLOSED;
        $this->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name_en', 'name_ar', 'sequence_order', 'start_date', 'end_date', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
