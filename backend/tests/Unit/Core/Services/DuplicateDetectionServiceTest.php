<?php

use App\Core\Services\DuplicateDetectionService;
use App\Core\ValueObjects\DuplicateSignals;
use App\Core\ValueObjects\IdentityDocumentReference;
use App\Core\ValueObjects\PersonName;

function nameOf(
    string $firstEn,
    string $familyEn,
    string $firstAr = 'أحمد',
    string $familyAr = 'الخطيب',
): PersonName {
    return new PersonName(
        firstNameEn: $firstEn,
        familyNameEn: $familyEn,
        firstNameAr: $firstAr,
        familyNameAr: $familyAr,
    );
}

it('scores an exact duplicate -- same name, dob, nationality, and identity document -- as certain', function () {
    $service = new DuplicateDetectionService;

    $document = new IdentityDocumentReference(documentType: 'national_id', issuingCountry: 'JO', number: '9988776655');

    $probe = new DuplicateSignals(
        name: nameOf('Mohammed', 'Al-Rashid'),
        dob: '2015-03-10',
        nationality: 'JO',
        identityDocuments: [$document],
    );
    $candidate = new DuplicateSignals(
        name: nameOf('Mohammed', 'Al-Rashid'),
        dob: '2015-03-10',
        nationality: 'JO',
        identityDocuments: [$document],
        subject: 'person-42',
    );

    $result = $service->score($probe, $candidate);

    expect($result->tier)->toBe(DuplicateDetectionService::TIER_CERTAIN)
        ->and($result->score)->toBe(100)
        ->and($result->subject)->toBe('person-42');
});

it('recognizes common English transliteration variants of the same Arabic name as a strong match', function (string $variantA, string $variantB) {
    $service = new DuplicateDetectionService;

    $probe = new DuplicateSignals(name: nameOf($variantA, 'Al-Rashid'), dob: '2015-03-10', nationality: 'JO');
    $candidate = new DuplicateSignals(name: nameOf($variantB, 'Al-Rashid'), dob: '2015-03-10', nationality: 'JO');

    $result = $service->score($probe, $candidate);

    // No identity document evidence on either side, so this must land in
    // "likely" (needs human review), never "certain" -- see the twins test.
    expect($result->tier)->toBe(DuplicateDetectionService::TIER_LIKELY)
        ->and($result->breakdown['first_name'])->toBe(20);
})->with([
    ['Mohammed', 'Muhammad'],
    ['Mohammed', 'Mohamed'],
    ['Muhammad', 'Muhammed'],
]);

it('never scores twins -- same family name, same dob, same nationality, different first names -- as a hard duplicate', function () {
    $service = new DuplicateDetectionService;

    $probe = new DuplicateSignals(name: nameOf('Layla', 'Al-Rashid'), dob: '2018-06-01', nationality: 'JO');
    $candidate = new DuplicateSignals(name: nameOf('Lina', 'Al-Rashid'), dob: '2018-06-01', nationality: 'JO');

    $result = $service->score($probe, $candidate);

    expect($result->tier)->not->toBe(DuplicateDetectionService::TIER_CERTAIN);
});

it('never reaches the certain tier from name plus dob plus nationality alone, even in the worst case', function () {
    $service = new DuplicateDetectionService;

    // Deliberately identical name/dob/nationality on both sides -- the
    // most adversarial case for the "twins" safety property, still
    // structurally capped below the certain threshold without any
    // identity-document evidence.
    $probe = new DuplicateSignals(name: nameOf('Sara', 'Al-Rashid'), dob: '2018-06-01', nationality: 'JO');
    $candidate = new DuplicateSignals(name: nameOf('Sara', 'Al-Rashid'), dob: '2018-06-01', nationality: 'JO');

    $result = $service->score($probe, $candidate);

    expect($result->score)->toBe(70)
        ->and($result->tier)->toBe(DuplicateDetectionService::TIER_LIKELY);
});

it('scores unrelated persons as none and excludes them from rank()', function () {
    $service = new DuplicateDetectionService;

    $probe = new DuplicateSignals(name: nameOf('Ahmad', 'Al-Rashid'), dob: '1990-01-01', nationality: 'JO');
    $unrelated = new DuplicateSignals(name: nameOf('Fatima', 'Nasser'), dob: '2005-11-20', nationality: 'EG');

    $result = $service->score($probe, $unrelated);

    expect($result->tier)->toBe(DuplicateDetectionService::TIER_NONE)
        ->and($service->rank($probe, [$unrelated]))->toBeEmpty();
});

it('ranks candidates highest score first and omits none-tier results', function () {
    $service = new DuplicateDetectionService;

    $document = new IdentityDocumentReference(documentType: 'national_id', issuingCountry: 'JO', number: '111');

    $probe = new DuplicateSignals(
        name: nameOf('Ahmad', 'Al-Rashid'), dob: '1990-01-01', nationality: 'JO',
        identityDocuments: [$document],
    );

    $exactMatch = new DuplicateSignals(
        name: nameOf('Ahmad', 'Al-Rashid'), dob: '1990-01-01', nationality: 'JO',
        identityDocuments: [$document], subject: 'certain-candidate',
    );
    $partialMatch = new DuplicateSignals(
        name: nameOf('Ahmad', 'Al-Rashid'), dob: '1990-01-01', nationality: 'JO', subject: 'likely-candidate',
    );
    $unrelated = new DuplicateSignals(name: nameOf('Fatima', 'Nasser'), dob: '2005-11-20', nationality: 'EG', subject: 'unrelated');

    $ranked = $service->rank($probe, [$unrelated, $partialMatch, $exactMatch]);

    expect($ranked)->toHaveCount(2)
        ->and($ranked[0]->subject)->toBe('certain-candidate')
        ->and($ranked[1]->subject)->toBe('likely-candidate');
});

it('computes an identical search key for known transliteration variants', function () {
    $service = new DuplicateDetectionService;

    $keyMohammed = $service->computeSearchKey(nameOf('Mohammed', 'Al-Rashid'));
    $keyMuhammad = $service->computeSearchKey(nameOf('Muhammad', 'Al-Rashid'));

    expect($keyMohammed)->toBe($keyMuhammad)
        ->and($keyMohammed)->not->toBe('');
});

it('computes different search keys for genuinely different names', function () {
    $service = new DuplicateDetectionService;

    $keyAhmad = $service->computeSearchKey(nameOf('Ahmad', 'Al-Rashid'));
    $keyFatima = $service->computeSearchKey(nameOf('Fatima', 'Nasser'));

    expect($keyAhmad)->not->toBe($keyFatima);
});
