<?php

use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonIdentityDocument;
use Illuminate\Database\QueryException;

function personForDocuments(): Person
{
    return Person::create([
        'first_name_en' => 'Ahmad', 'family_name_en' => 'Al-Rashid',
        'first_name_ar' => 'أحمد', 'family_name_ar' => 'الرشيد',
        'dob' => '1990-05-12', 'gender' => Person::GENDER_MALE, 'nationality' => 'JO',
    ]);
}

it('rejects a duplicate document_type + issuing_country + number triple', function () {
    $person = personForDocuments();

    PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'JO', 'number' => 'A1234567',
    ]);

    PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'JO', 'number' => 'A1234567',
    ]);
})->throws(QueryException::class);

it('allows the same number under a different document type or issuing country', function () {
    $person = personForDocuments();

    $passport = PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'JO', 'number' => '12345',
    ]);
    $nationalId = PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'national_id', 'issuing_country' => 'JO', 'number' => '12345',
    ]);
    $otherCountry = PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'US', 'number' => '12345',
    ]);

    expect($passport->exists)->toBeTrue()
        ->and($nationalId->exists)->toBeTrue()
        ->and($otherCountry->exists)->toBeTrue();
});

it('keeps a renewed document as a new row, marking the old one no longer current', function () {
    $person = personForDocuments();

    $original = PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'JO',
        'number' => 'OLD0001', 'is_current' => true,
    ]);

    $original->update(['is_current' => false]);

    $renewed = PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'JO',
        'number' => 'NEW0002', 'is_current' => true,
    ]);

    expect($original->fresh()->is_current)->toBeFalse()
        ->and($renewed->is_current)->toBeTrue()
        ->and($person->identityDocuments()->count())->toBe(2);
});

it('converts to an IdentityDocumentReference matching its stored fields', function () {
    $person = personForDocuments();

    $document = PersonIdentityDocument::create([
        'person_id' => $person->id, 'document_type' => 'passport', 'issuing_country' => 'JO', 'number' => 'A1234567',
    ]);

    $reference = $document->toReference();

    expect($reference->documentType)->toBe('passport')
        ->and($reference->issuingCountry)->toBe('JO')
        ->and($reference->number)->toBe('A1234567');
});
