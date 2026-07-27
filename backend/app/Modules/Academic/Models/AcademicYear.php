<?php

namespace App\Modules\Academic\Models;

use App\Core\Concerns\HasPublicId;
use App\Modules\Academic\Events\AcademicYearClosed;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The org-wide academic calendar cycle every Enrollment/Applicant/
 * Position Assignment period ultimately anchors to (Sprint 4.1
 * Technical Specification). Not branch-scoped -- a single academic
 * calendar spans the whole organization, the same way Academic Year is
 * described platform-wide in docs/business-domains/academic.md.
 */
class AcademicYear extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name_en', 'name_ar', 'start_date', 'end_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function newFactory(): AcademicYearFactory
    {
        return AcademicYearFactory::new();
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
     * Idempotent -- closing an already-closed year is a no-op, not an
     * exception, matching the platform's general non-destructive-
     * transition discipline. Fires AcademicYearClosed exactly once per
     * real transition.
     */
    public function close(): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->status = self::STATUS_CLOSED;
        $this->save();

        AcademicYearClosed::dispatch($this);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name_en', 'name_ar', 'start_date', 'end_date', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
