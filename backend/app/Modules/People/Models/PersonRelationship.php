<?php

namespace App\Modules\People\Models;

use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use Database\Factories\PersonRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A generic, directed Person-to-Person relationship graph
 * (docs/DOMAIN_BLUEPRINT.md §11, ADR-0003) -- informational, not a
 * Family aggregate. `person_id`/`related_person_id` are deliberately
 * neutral names, never `parent_id`/`child_id`: this table encodes no
 * hierarchy, ownership, or generational assumption of its own. All
 * business meaning -- sibling, spouse, former spouse, paternal/maternal
 * uncle or grandfather, guardian, relative, or any future kind -- comes
 * entirely from `relationship_type_id`.
 *
 * Different from guardian_student deliberately: no custody/pickup
 * state, no verification, no effective-dating -- a flat fact that
 * exists or doesn't, correctable via ordinary Activitylog-audited hard
 * delete, the same category as Contacts/Addresses (high-churn,
 * low-stakes child records), not the true-soft-delete or
 * effective-dated categories §7 reserves for higher-stakes facts. No
 * `public_id`: this is a shallow generic edge, the same "pivot, not
 * aggregate" treatment already given to Tags' `taggables` (Addendum D2),
 * not a Domain aggregate under Addendum D4's dual-ID rule.
 *
 * Deliberately does NOT attempt to resolve or deduplicate the inverse
 * direction (e.g. recognizing that a second "sibling" row from the
 * other side restates the same fact, or that "uncle" reversed should
 * read as "nephew") -- that requires relationship_type-level knowledge
 * (which types are symmetric, what a type's inverse label is) that
 * doesn't exist yet, and is Family-tree read-model work the Playbook
 * explicitly defers past this sprint. This model only guarantees the
 * row is discoverable querying from either side.
 */
class PersonRelationship extends Model implements ReassignsIdentityReferences, RedactsPersonalData
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'person_id',
        'related_person_id',
        'relationship_type_id',
    ];

    protected static function newFactory(): PersonRelationshipFactory
    {
        return PersonRelationshipFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $relationship): void {
            if ($relationship->person_id === $relationship->related_person_id) {
                throw new InvalidArgumentException(
                    'PersonRelationship: a person cannot hold a relationship to themselves.'
                );
            }

            $type = RelationshipType::find($relationship->relationship_type_id);

            if ($type !== null && $type->scope !== RelationshipType::SCOPE_PERSON_RELATIONSHIP) {
                throw new InvalidArgumentException(
                    "PersonRelationship: relationship type '{$type->code}' belongs to the "
                    ."'{$type->scope}' scope, not '".RelationshipType::SCOPE_PERSON_RELATIONSHIP."'."
                );
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class);
    }

    /**
     * Unlike GuardianStudent, person_id/related_person_id are direct
     * Person references -- a real reassignment, mirroring Person's own
     * Sprint 2.1 precedent. Both sides are updated independently since a
     * merged Person could appear on either side of a stored row.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId): void
    {
        static::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
        static::where('related_person_id', $oldPersonId)->update(['related_person_id' => $newPersonId]);
    }

    /**
     * A deliberate no-op: this row holds no personally-identifying field
     * of its own beyond the Person references themselves, which
     * anonymization does not remove (the relationship fact, e.g. "these
     * two were siblings," is not PII in the sense this contract targets).
     */
    public function anonymizePerson(int $personId): void
    {
        // Intentionally empty -- see docblock above.
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['person_id', 'related_person_id', 'relationship_type_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
