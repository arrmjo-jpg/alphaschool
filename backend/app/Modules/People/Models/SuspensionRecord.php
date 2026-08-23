<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Models\ReasonCode;
use Database\Factories\SuspensionRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A suspension sub-status on one Enrollment (Sprint 4.3 Technical
 * Specification) -- Suspension is never a new Enrollment row, per
 * frozen docs/DOMAIN_BLUEPRINT.md. No business logic of its own; the
 * actions that create/close these records are Sprint 4.4's own scope.
 */
class SuspensionRecord extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'enrollment_id', 'reason_code_id', 'suspended_at', 'reinstated_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'reinstated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SuspensionRecordFactory
    {
        return SuspensionRecordFactory::new();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }
}
