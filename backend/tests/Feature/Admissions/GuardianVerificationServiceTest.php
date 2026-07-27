<?php

use App\Core\ValueObjects\PersonName;
use App\Modules\Admissions\Services\GuardianVerificationService;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonIdentityDocument;
use Illuminate\Support\Facades\DB;

function personWithIdentityDocument(string $firstNameEn, string $familyNameEn): Person
{
    $person = Person::factory()->create([
        'first_name_en' => $firstNameEn,
        'family_name_en' => $familyNameEn,
        'first_name_ar' => 'أحمد',
        'family_name_ar' => 'حسن',
    ]);

    PersonIdentityDocument::create([
        'person_id' => $person->id,
        'document_type' => 'passport',
        'issuing_country' => 'JO',
        'number' => 'P'.$person->id.'000',
        'is_current' => true,
    ]);

    return $person;
}

/**
 * Independent Review regression test -- toSignals() previously accessed
 * ->identityDocuments per candidate inside the duplicate-detection
 * scoring loop, lazy-loading once per row instead of eager-loading once
 * total. Three candidates sharing the probe's search_key exercise the
 * loop with more than one row -- without the fix, this produces 3
 * separate person_identity_documents queries instead of 1.
 */
it('issues exactly one identity-document query regardless of duplicate-detection candidate count', function () {
    personWithIdentityDocument('Ahmad', 'Hassan');
    personWithIdentityDocument('Ahmad', 'Hassan');
    personWithIdentityDocument('Ahmad', 'Hassan');

    $service = app(GuardianVerificationService::class);
    $probeName = new PersonName(firstNameEn: 'Ahmad', familyNameEn: 'Hassan', firstNameAr: 'أحمد', familyNameAr: 'حسن');

    DB::enableQueryLog();

    $service->resolveApplicantPerson($probeName, '2019-01-01', 'JO', Person::GENDER_MALE);

    $queries = DB::getQueryLog();
    DB::flushQueryLog();
    DB::disableQueryLog();

    $identityDocumentQueries = collect($queries)->filter(
        fn (array $query) => str_contains($query['query'], 'person_identity_documents')
    );

    expect($identityDocumentQueries)->toHaveCount(1);
});
