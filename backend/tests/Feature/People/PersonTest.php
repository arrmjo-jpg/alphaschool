<?php

use App\Modules\People\Models\Address;
use App\Modules\People\Models\Contact;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonIdentityDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function makePerson(array $overrides = []): Person
{
    return Person::create(array_merge([
        'first_name_en' => 'Ahmad',
        'family_name_en' => 'Al-Rashid',
        'first_name_ar' => 'أحمد',
        'family_name_ar' => 'الرشيد',
        'dob' => '1990-05-12',
        'gender' => Person::GENDER_MALE,
        'nationality' => 'JO',
    ], $overrides));
}

it('creates a person with full bilingual identity data', function () {
    $person = makePerson([
        'second_name_en' => 'Yousef',
        'second_name_ar' => 'يوسف',
    ]);

    expect($person->name()->fullNameEn())->toBe('Ahmad Yousef Al-Rashid')
        ->and($person->name()->fullNameAr())->toBe('أحمد يوسف الرشيد')
        ->and($person->dob->toDateString())->toBe('1990-05-12')
        ->and($person->gender)->toBe('male')
        ->and($person->nationality)->toBe('JO');
});

it('assigns a ulid public_id, never exposing the raw internal id as the route key', function () {
    $person = makePerson();

    expect(Str::isUlid($person->public_id))->toBeTrue()
        ->and($person->getRouteKeyName())->toBe('public_id');
});

it('computes and stores a search_key automatically on save', function () {
    $person = makePerson();

    expect($person->search_key)->not->toBeEmpty();
});

it('recomputes the search_key when the name changes', function () {
    $person = makePerson();
    $originalKey = $person->search_key;

    $person->update(['first_name_en' => 'Khalid']);

    expect($person->search_key)->not->toBe($originalKey);
});

it('soft deletes rather than hard deletes', function () {
    $person = makePerson();
    $id = $person->id;

    $person->delete();

    expect(Person::find($id))->toBeNull()
        ->and(Person::withTrashed()->find($id))->not->toBeNull();
});

it('owns contacts, addresses, and identity documents as separate child entities, not columns on itself', function () {
    $person = makePerson();

    Contact::create(['person_id' => $person->id, 'type' => Contact::TYPE_PHONE, 'value' => '+962700000000', 'is_primary' => true]);
    Address::create(['person_id' => $person->id, 'type' => Address::TYPE_HOME, 'line1' => 'Street 1', 'city' => 'Amman', 'country' => 'JO']);
    PersonIdentityDocument::create(['person_id' => $person->id, 'document_type' => 'national_id', 'issuing_country' => 'JO', 'number' => '9988776655']);

    expect($person->contacts()->count())->toBe(1)
        ->and($person->addresses()->count())->toBe(1)
        ->and($person->identityDocuments()->count())->toBe(1);
});

it('marks a contact verified only once verified_at is set', function () {
    $person = makePerson();
    $contact = Contact::create(['person_id' => $person->id, 'type' => Contact::TYPE_PHONE, 'value' => '+962700000000']);

    expect($contact->isVerified())->toBeFalse();

    $contact->update(['verified_at' => now()]);

    expect($contact->fresh()->isVerified())->toBeTrue();
});

it('stores the person photo on the private disk with no branch segment in the path', function () {
    Storage::fake('private');

    $person = makePerson();
    $tempFile = tempnam(sys_get_temp_dir(), 'person-photo-');
    file_put_contents($tempFile, 'fake image content');

    $media = $person->addMedia($tempFile)
        ->preservingOriginal()
        ->toMediaCollection('photo');

    expect($media->disk)->toBe('private')
        ->and($media->getPathRelativeToRoot())
        ->toBe("private/Person/{$person->id}/photo/{$media->id}-{$media->file_name}");

    @unlink($tempFile);
});

it('reassigns its own children when a merge moves them to another person', function () {
    $oldPerson = makePerson();
    $newPerson = makePerson(['first_name_en' => 'Sara', 'first_name_ar' => 'سارة']);

    $contact = Contact::create(['person_id' => $oldPerson->id, 'type' => Contact::TYPE_PHONE, 'value' => '+962700000000']);
    $address = Address::create(['person_id' => $oldPerson->id, 'type' => Address::TYPE_HOME, 'line1' => 'Street 1', 'city' => 'Amman', 'country' => 'JO']);
    $document = PersonIdentityDocument::create(['person_id' => $oldPerson->id, 'document_type' => 'national_id', 'issuing_country' => 'JO', 'number' => '111']);

    $oldPerson->reassignPerson($oldPerson->id, $newPerson->id);

    expect($contact->fresh()->person_id)->toBe($newPerson->id)
        ->and($address->fresh()->person_id)->toBe($newPerson->id)
        ->and($document->fresh()->person_id)->toBe($newPerson->id);
});

it('redacts identifying fields when anonymized', function () {
    $person = makePerson();

    $person->anonymizePerson($person->id);

    $redacted = $person->fresh();

    expect($redacted->first_name_en)->toBe('Redacted')
        ->and($redacted->family_name_en)->toBe('Redacted')
        ->and($redacted->nationality)->toBeNull();
});

it('logs identity field changes via activitylog, suppressing empty diffs', function () {
    $person = makePerson();

    $person->update(['first_name_en' => 'Khalid']);

    expect($person->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $person->activitiesAsSubject()->count();
    $person->update(['first_name_en' => 'Khalid']); // no real change
    expect($person->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
