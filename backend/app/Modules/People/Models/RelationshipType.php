<?php

namespace App\Modules\People\Models;

use Database\Factories\RelationshipTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Translatable\HasTranslations;

/**
 * The shared, translatable vocabulary for both guardian_student's
 * relationship roles (Father, Mother, Legal Guardian) and
 * person_relationships' kinship terms (Sibling, Uncle, Grandfather),
 * distinguished by `scope` -- one lookup table, not two, the same
 * pattern already applied to Tags (Addendum D2).
 *
 * Exists specifically so Arabic's paternal/maternal kinship
 * distinctions (عم vs خال, جد لأب vs جد لأم) are genuinely separate
 * rows -- English can't represent this as one enum case with two
 * labels (docs/DOMAIN_BLUEPRINT.md §11).
 *
 * `code` is immutable once set and is the only value business code
 * may ever reference -- never `name` (translated, and never stable
 * across locales). Retired values are deactivated (`is_active`), never
 * deleted, since historical guardian_student/person_relationships rows
 * must remain valid indefinitely. This is enforced in three layers:
 * the application workflow exposes only Activate/Deactivate, never
 * Delete; this model refuses delete() outright (below); and once
 * guardian_student/person_relationships exist (Step 2), their foreign
 * keys to this table must use restrictOnDelete(), never cascade/null,
 * as the database-level backstop.
 */
class RelationshipType extends Model
{
    use HasFactory;
    use HasTranslations;

    public const SCOPE_GUARDIAN_STUDENT = 'guardian_student';

    public const SCOPE_PERSON_RELATIONSHIP = 'person_relationship';

    public array $translatable = ['name'];

    protected $fillable = ['code', 'name', 'scope', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): RelationshipTypeFactory
    {
        return RelationshipTypeFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $relationshipType): void {
            if ($relationshipType->exists && $relationshipType->isDirty('code')) {
                throw new InvalidArgumentException(
                    'RelationshipType: code is immutable once set -- referenced by guardian_student and person_relationships rows that must remain valid indefinitely.'
                );
            }
        });

        static::deleting(function (self $relationshipType): void {
            throw new RuntimeException(
                'RelationshipType: reference data is never physically deleted -- deactivate it instead (is_active = false) so historical guardian_student/person_relationships rows remain valid.'
            );
        });
    }

    public function scopeOfScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWhereCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }
}
