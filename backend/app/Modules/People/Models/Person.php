<?php

namespace App\Modules\People\Models;

use App\Core\Concerns\HasPublicId;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Core\Services\DuplicateDetectionService;
use App\Core\ValueObjects\PersonName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The identity substrate (docs/DOMAIN_BLUEPRINT.md §3/§8): the stable
 * aggregate every context (Employee/Student/Guardian/Applicant, Phase 2
 * onward) references by ID. Owns ONLY name, DOB, gender, nationality,
 * and photo -- contacts, addresses, and identity documents are separate
 * child entities (see Contact/Address/PersonIdentityDocument), never
 * columns here, per the agreed pre-implementation design.
 *
 * Never branch-scoped (Addendum B6): a Person's branch relevance always
 * flows through a context aggregate (Enrollment, Employment), never a
 * column here -- this is also why the `photo` collection below does not
 * implement Media's HasBranchScopedMedia contract.
 */
class Person extends Model implements HasMedia, ReassignsIdentityReferences, RedactsPersonalData
{
    use HasPublicId;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    protected $fillable = [
        'first_name_en', 'first_name_ar',
        'second_name_en', 'second_name_ar',
        'third_name_en', 'third_name_ar',
        'family_name_en', 'family_name_ar',
        'dob', 'gender', 'nationality',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $person): void {
            $person->search_key = app(DuplicateDetectionService::class)->computeSearchKey($person->name());
        });
    }

    /**
     * Builds the structured, bilingual name value object from this
     * Person's flat columns -- the columns are the storage shape, this
     * is the shape everything else (formatting, duplicate detection)
     * actually works with.
     */
    public function name(): PersonName
    {
        return new PersonName(
            firstNameEn: $this->first_name_en,
            familyNameEn: $this->family_name_en,
            firstNameAr: $this->first_name_ar,
            familyNameAr: $this->family_name_ar,
            secondNameEn: $this->second_name_en,
            secondNameAr: $this->second_name_ar,
            thirdNameEn: $this->third_name_en,
            thirdNameAr: $this->third_name_ar,
        );
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function identityDocuments(): HasMany
    {
        return $this->hasMany(PersonIdentityDocument::class);
    }

    public function registerMediaCollections(): void
    {
        // Private, not public: a person's photo is not the kind of file
        // a CDN should ever cache/serve unauthenticated
        // (docs/DOMAIN_BLUEPRINT.md §12).
        $this->addMediaCollection('photo')
            ->useDisk('private')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photo')
            ->width(300)
            ->height(300);
    }

    /**
     * Trivial per Addendum C3/the Playbook's Sprint 2.1 scope: at this
     * point in the build, Person's own children (contacts, addresses,
     * identity documents) are the only things that need reassigning --
     * Employee/Student/Guardian implement their own copies of this
     * contract once they exist (Sprint 2.4) and have their own
     * references to move.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId): void
    {
        Contact::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
        Address::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
        PersonIdentityDocument::where('person_id', $oldPersonId)->update(['person_id' => $newPersonId]);
    }

    /**
     * Trivial per the same Sprint 2.1 scope note above -- redacts this
     * Person's own identifying fields. Full anonymization (sensitivity-
     * aware Media redaction, approval gating, irreversibility) is Phase 3
     * (Sprint 3.3).
     */
    public function anonymizePerson(int $personId): void
    {
        static::where('id', $personId)->update([
            'first_name_en' => 'Redacted', 'first_name_ar' => 'محذوف',
            'second_name_en' => null, 'second_name_ar' => null,
            'third_name_en' => null, 'third_name_ar' => null,
            'family_name_en' => 'Redacted', 'family_name_ar' => 'محذوف',
            'nationality' => null,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'first_name_en', 'first_name_ar', 'second_name_en', 'second_name_ar',
                'third_name_en', 'third_name_ar', 'family_name_en', 'family_name_ar',
                'dob', 'gender', 'nationality',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
