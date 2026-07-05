<?php

namespace App\Modules\Identity\Models;

use App\Core\Concerns\HasPublicId;
use App\Modules\People\Models\Person;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
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
}
