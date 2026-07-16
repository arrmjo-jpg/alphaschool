<?php

namespace App\Modules\Administration\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The Credential Vault's own row -- one encrypted version per write,
 * never overwritten (docs/adr/0019-integration-platform-architecture.md
 * Decision 5). Written and read exclusively through
 * App\Modules\Administration\Services\ProviderCredentialVault.
 *
 * `credentials` uses Laravel's built-in encrypted cast -- ciphertext at
 * rest, decrypted only inside the Vault service's own return value.
 *
 * Cheap-tier audit, deliberately: `logOnly([])` records who/when
 * (causer, event, timestamp) but never a `credentials` diff, in either
 * direction -- Decision 5's explicit requirement, and the direct
 * contrast with ConfigurationDefinition/ConfigurationValue's full-diff
 * tier. The Vault's own version history (never-overwritten rows) is
 * what carries "what changed"; the Audit Engine only ever needs to
 * answer "who, when."
 */
class ProviderCredential extends Model
{
    use HasFactory;
    use HasPublicId;
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const ALTITUDE_GLOBAL = 'global';

    public const ALTITUDE_BRANCH = 'branch';

    protected $fillable = [
        'slot_key', 'altitude', 'branch_id', 'credentials', 'version',
        'status', 'approval_request_id', 'effective_from', 'effective_until',
        'updated_by_id',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
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
        return LogOptions::defaults()->logOnly([]);
    }
}
