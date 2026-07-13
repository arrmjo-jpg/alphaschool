<?php

namespace App\Modules\IdentityMaintenance\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\Models\ApprovalRequest;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Person;
use Database\Factories\MergeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The highest-stakes operation in the system (Addendum C4/C7-C10,
 * Sprint 3.2). `duplicate_flag_id` is deliberately nullable -- a
 * MergeRequest may originate from a reviewed DuplicateFlag, or from a
 * manual/API/future-import request; the model and its orchestration
 * never assume a flag exists.
 *
 * State machine (every transition explicit, none implicit or
 * controller-defined -- enforced below, not just documented):
 *
 *   draft --------------------> dry_run_passed
 *   draft --------------------> draft            (dry run failed, retryable)
 *   draft --------------------> cancelled
 *   dry_run_passed ------------> pending_approval
 *   dry_run_passed ------------> cancelled
 *   pending_approval -----------> approved
 *   pending_approval -----------> rejected
 *   pending_approval -----------> cancelled
 *   approved -------------------> executing
 *   executing -------------------> executed
 *   executing -------------------> failed
 *   failed --------------------> approved         (retry after investigation)
 *   executed -------------------> rollback_requested
 *   rollback_requested ----------> rollback_approved
 *   rollback_requested ----------> rollback_rejected
 *   rollback_requested ----------> rollback_cancelled
 *   rollback_approved -----------> rolling_back
 *   rolling_back -----------------> rolled_back
 *   rolling_back -----------------> rollback_failed   (EMERGENCY TERMINAL --
 *                                                       zero outgoing
 *                                                       transitions; recovery
 *                                                       is a manual,
 *                                                       out-of-band procedure,
 *                                                       never a modeled
 *                                                       application action)
 *   rollback_rejected -----------> rollback_requested
 *   rollback_cancelled ----------> rollback_requested
 *
 * Terminal (no outgoing transitions at all): rejected, cancelled,
 * rolled_back, rollback_failed.
 *
 * Implements both Identity Maintenance contracts as deliberate no-ops:
 * this is an immutable historical record ("at this point in time,
 * Person X merged into Person Y"). If Person Y is later involved in a
 * DIFFERENT, later merge, this record's winning_person_id must NOT be
 * rewritten to track that -- doing so would silently rewrite history,
 * exactly what this entire project's "never overwrite history"
 * principle (§7) exists to prevent. MergeReassignmentLog declares
 * OwnedByAggregate against this class for the identical reason.
 *
 * anonymizePerson() is also a no-op for now, with a known Sprint 3.3
 * gap recorded here rather than silently ignored: losing_person_snapshot
 * is a frozen JSON copy of the losing Person's fields, which will need
 * its own redaction logic once real Anonymization (Sprint 3.3) exists
 * and could target a Person referenced here.
 */
class MergeRequest extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DRY_RUN_PASSED = 'dry_run_passed';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLBACK_REQUESTED = 'rollback_requested';

    public const STATUS_ROLLBACK_APPROVED = 'rollback_approved';

    public const STATUS_ROLLING_BACK = 'rolling_back';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_ROLLBACK_REJECTED = 'rollback_rejected';

    public const STATUS_ROLLBACK_CANCELLED = 'rollback_cancelled';

    public const STATUS_ROLLBACK_FAILED = 'rollback_failed';

    /**
     * @var array<string, string[]>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_DRAFT, self::STATUS_DRY_RUN_PASSED, self::STATUS_CANCELLED],
        self::STATUS_DRY_RUN_PASSED => [self::STATUS_PENDING_APPROVAL, self::STATUS_CANCELLED],
        self::STATUS_PENDING_APPROVAL => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_EXECUTING],
        self::STATUS_EXECUTING => [self::STATUS_EXECUTED, self::STATUS_FAILED],
        self::STATUS_FAILED => [self::STATUS_APPROVED],
        self::STATUS_EXECUTED => [self::STATUS_ROLLBACK_REQUESTED],
        self::STATUS_ROLLBACK_REQUESTED => [self::STATUS_ROLLBACK_APPROVED, self::STATUS_ROLLBACK_REJECTED, self::STATUS_ROLLBACK_CANCELLED],
        self::STATUS_ROLLBACK_APPROVED => [self::STATUS_ROLLING_BACK],
        self::STATUS_ROLLING_BACK => [self::STATUS_ROLLED_BACK, self::STATUS_ROLLBACK_FAILED],
        self::STATUS_ROLLBACK_REJECTED => [self::STATUS_ROLLBACK_REQUESTED],
        self::STATUS_ROLLBACK_CANCELLED => [self::STATUS_ROLLBACK_REQUESTED],
        self::STATUS_REJECTED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_ROLLED_BACK => [],
        self::STATUS_ROLLBACK_FAILED => [],
    ];

    protected $fillable = [
        'losing_person_id',
        'winning_person_id',
        'duplicate_flag_id',
        'status',
        'requested_by_id',
        'approval_request_id',
        'rollback_approval_request_id',
        'losing_person_snapshot',
        'last_dry_run_result',
        'last_dry_run_at',
        'decided_at',
        'executed_at',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'losing_person_snapshot' => 'array',
            'last_dry_run_result' => 'array',
            'last_dry_run_at' => 'datetime',
            'decided_at' => 'datetime',
            'executed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MergeRequestFactory
    {
        return MergeRequestFactory::new();
    }

    /**
     * "No transition should be implicit or controller-defined" -- this
     * guard is the single source of truth for what's allowed; a
     * controller/service may request a transition, only this model
     * decides whether it's legal.
     */
    protected static function booted(): void
    {
        static::saving(function (self $mergeRequest): void {
            if (! $mergeRequest->exists || ! $mergeRequest->isDirty('status')) {
                return;
            }

            $from = $mergeRequest->getOriginal('status');
            $to = $mergeRequest->status;
            $allowed = self::TRANSITIONS[$from] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw new RuntimeException(
                    "MergeRequest #{$mergeRequest->id}: illegal status transition from '{$from}' to '{$to}'."
                );
            }
        });
    }

    public function losingPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'losing_person_id');
    }

    public function winningPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'winning_person_id');
    }

    public function duplicateFlag(): BelongsTo
    {
        return $this->belongsTo(DuplicateFlag::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function rollbackApprovalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'rollback_approval_request_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MergeReassignmentLog::class);
    }

    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void
    {
        // Intentionally empty -- see class docblock. An immutable
        // historical record must never be rewritten by a later merge.
    }

    /**
     * @return ReassignmentImpact[]
     */
    public function previewReassignment(int $oldPersonId, int $newPersonId): array
    {
        return [];
    }

    public function anonymizePerson(int $personId): void
    {
        // Intentionally empty -- see class docblock's Sprint 3.3 note.
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'decided_at', 'executed_at', 'rolled_back_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
