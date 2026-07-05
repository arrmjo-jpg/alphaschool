<?php

use App\Core\ValueObjects\IdentityDocumentReference;

it('considers two references equal only when all three parts match', function () {
    $a = new IdentityDocumentReference(documentType: 'passport', issuingCountry: 'JO', number: 'A1234567');
    $b = new IdentityDocumentReference(documentType: 'passport', issuingCountry: 'JO', number: 'A1234567');

    expect($a->equals($b))->toBeTrue();
});

it('does not treat the same number under a different document type or country as equal', function () {
    $passport = new IdentityDocumentReference(documentType: 'passport', issuingCountry: 'JO', number: '12345');
    $nationalId = new IdentityDocumentReference(documentType: 'national_id', issuingCountry: 'JO', number: '12345');
    $otherCountry = new IdentityDocumentReference(documentType: 'passport', issuingCountry: 'US', number: '12345');

    expect($passport->equals($nationalId))->toBeFalse()
        ->and($passport->equals($otherCountry))->toBeFalse();
});

it('rejects a blank document type, issuing country, or number', function (array $args) {
    new IdentityDocumentReference(...$args);
})->with([
    'blank document type' => [['documentType' => '', 'issuingCountry' => 'JO', 'number' => '123']],
    'blank issuing country' => [['documentType' => 'passport', 'issuingCountry' => '  ', 'number' => '123']],
    'blank number' => [['documentType' => 'passport', 'issuingCountry' => 'JO', 'number' => '']],
])->throws(InvalidArgumentException::class);

it('stringifies as a slash-separated triple', function () {
    $ref = new IdentityDocumentReference(documentType: 'passport', issuingCountry: 'JO', number: 'A1234567');

    expect((string) $ref)->toBe('passport/JO/A1234567');
});
