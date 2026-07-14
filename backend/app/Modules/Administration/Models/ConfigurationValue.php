<?php

namespace App\Modules\Administration\Models;

use App\Core\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One resolved value at one altitude (docs/adr/0018 Decision 4). Written
 * and read exclusively through
 * App\Modules\Administration\Services\SettingsResolver -- never a
 * direct create()/update() elsewhere, since the Resolver is what
 * enforces optimistic locking (Decision 8), permission checks
 * (Decision 9), and approval routing (Decision 10's approval_permission).
 *
 * `branch_id` null means Platform/Deployment/Organization altitude
 * (this dedicated-instance-per-customer model, ADR-0006, collapses
 * those three into one effective global row -- see ADR-0018 Decision 4
 * and the Foundation Track's own Playbook note on this); a non-null
 * `branch_id` is the Branch-altitude override.
 */
class ConfigurationValue extends Model
{
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const ALTITUDE_GLOBAL = 'global';

    public const ALTITUDE_BRANCH = 'branch';

    protected $fillable = [
        'configuration_key', 'altitude', 'branch_id', 'value', 'version',
        'status', 'approval_request_id', 'effective_from', 'effective_until',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
