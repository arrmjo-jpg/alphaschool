<?php

namespace App\Modules\People\Models;

use App\Core\ValueObjects\IdentityDocumentReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A real-world identity-document reissue (docs/DOMAIN_BLUEPRINT.md §7)
 * is NEVER an overwrite of the previous value -- a passport renewal is a
 * new row with a new number, with the old row kept and marked
 * `is_current = false`. Uniqueness is scoped to the whole
 * (document_type, issuing_country, number) triple at the database level
 * (see the migration), matching App\Core\ValueObjects\IdentityDocumentReference.
 *
 * Distinguishes real-world reissues from data-entry corrections (typo
 * fixes to an existing row, protected by Activitylog + a required reason
 * -- Phase 3's Identity Correction tiering, not built here).
 */
class PersonIdentityDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_id', 'document_type', 'issuing_country', 'number',
        'issued_at', 'expires_at', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function toReference(): IdentityDocumentReference
    {
        return new IdentityDocumentReference(
            documentType: $this->document_type,
            issuingCountry: $this->issuing_country,
            number: $this->number,
        );
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
