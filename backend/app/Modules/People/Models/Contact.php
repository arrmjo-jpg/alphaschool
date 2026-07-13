<?php

namespace App\Modules\People\Models;

use App\Core\Contracts\OwnedByAggregate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Person's contact channel (docs/DOMAIN_BLUEPRINT.md §5) -- a child
 * entity, never columns on Person, since a person has multiple contacts
 * and contact-change audit needs to be separately filterable from
 * identity-change audit (a phone-number change is a common OTP-hijack
 * precursor).
 *
 * OwnedByAggregate, not ReassignsIdentityReferences/RedactsPersonalData
 * directly: Person::reassignPerson() already cascades to this table
 * (Sprint 2.1) -- Identity Maintenance calls Person, the aggregate
 * root, never this child entity independently.
 */
class Contact extends Model implements OwnedByAggregate
{
    use SoftDeletes;

    public const TYPE_PHONE = 'phone';

    public const TYPE_EMAIL = 'email';

    protected $fillable = ['person_id', 'type', 'value', 'is_primary', 'verified_at'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function owningAggregate(): string
    {
        return Person::class;
    }
}
