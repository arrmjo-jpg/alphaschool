<?php

namespace App\Modules\Identity\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\People\Models\Person;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use RuntimeException;
use Spatie\Permission\Traits\HasRoles;

/**
 * Authentication only (docs/DOMAIN_BLUEPRINT.md §8/§15: "Authentication
 * is not Identity: User ≠ Person"). Holds a single one-way FK to
 * Person -- never gains a direct FK to Employee/Student/Guardian, which
 * "account type" a User has is always DERIVED (see
 * App\Modules\Identity\Services\AccountTypeResolver), never stored here
 * as an enum.
 *
 * Lives in the Identity Foundation module, not App\Models -- Identity
 * owns "User accounts (authentication only)" per Blueprint §1. The
 * default `App\Models\User` Laravel scaffolds on `laravel new` was never
 * an intentional placement decision, just an unclaimed default sitting
 * there until Identity's first real sprint.
 *
 * HasRoles (Sprint 2.3): Roles are Employee-only, globally defined, but
 * branch-scoped in *assignment* via Spatie Teams (team_foreign_key =
 * branch_id) -- direct permission-to-user grants are never exposed
 * anywhere in this codebase (docs/DOMAIN_BLUEPRINT.md §8: "never
 * granted directly to a user -- always through a role").
 */
class User extends Authenticatable implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'person_id', 'username', 'email', 'phone', 'password', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function markLoggedIn(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }

    /**
     * A genuine gap closed in Sprint 3.1 (found by the new schema
     * scanner, not previously implemented): person_id is unique
     * (one User account per Person). Structural validity is now
     * self-checked via $dryRun (Sprint 3.2) rather than assumed
     * pre-validated by an external caller.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void
    {
        if ($dryRun) {
            if (static::where('person_id', $newPersonId)->exists()) {
                throw new RuntimeException(
                    "User: person #{$newPersonId} already holds a User row -- reassigning person #{$oldPersonId} would violate the unique person_id constraint."
                );
            }

            return;
        }

        static::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
    }

    /**
     * @return ReassignmentImpact[]
     */
    public function previewReassignment(int $oldPersonId, int $newPersonId): array
    {
        $ids = static::where('person_id', $oldPersonId)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        return [new ReassignmentImpact(static::class, 'person_id', $ids, 'The User account would move.')];
    }

    /**
     * username/email are NOT NULL + unique, so redaction uses the row's
     * own id to stay unique rather than a fixed literal (which would
     * collide the second time this ever runs). phone is nullable and
     * simply cleared. Deliberately does not touch `status` -- whether
     * an anonymized account should also become unusable is a business
     * workflow decision for the future Anonymization request (Sprint
     * 3.3), not something this trivial per-model contract implementation
     * should decide unilaterally.
     */
    public function anonymizePerson(int $personId): void
    {
        $user = static::where('person_id', $personId)->first();

        if ($user === null) {
            return;
        }

        $user->forceFill([
            'username' => "redacted-{$user->id}",
            'email' => "redacted-{$user->id}@redacted.invalid",
            'phone' => null,
        ])->save();
    }
}
