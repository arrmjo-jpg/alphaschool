<?php

use App\Core\ValueObjects\PersonName;

it('builds full names from all present parts, in order', function () {
    $name = new PersonName(
        firstNameEn: 'Ahmad',
        familyNameEn: 'Al-Khatib',
        firstNameAr: 'أحمد',
        familyNameAr: 'الخطيب',
        secondNameEn: 'Yousef',
        secondNameAr: 'يوسف',
        thirdNameEn: 'Ibrahim',
        thirdNameAr: 'إبراهيم',
    );

    expect($name->fullNameEn())->toBe('Ahmad Yousef Ibrahim Al-Khatib')
        ->and($name->fullNameAr())->toBe('أحمد يوسف إبراهيم الخطيب');
});

it('omits second and third name parts entirely when absent, not as blank segments', function () {
    $name = new PersonName(
        firstNameEn: 'Ahmad',
        familyNameEn: 'Al-Khatib',
        firstNameAr: 'أحمد',
        familyNameAr: 'الخطيب',
    );

    expect($name->fullNameEn())->toBe('Ahmad Al-Khatib')
        ->and($name->fullNameEn())->not->toContain('  ');
});

it('rejects a missing required first or family name in either language', function (array $overrides) {
    $base = [
        'firstNameEn' => 'Ahmad',
        'familyNameEn' => 'Al-Khatib',
        'firstNameAr' => 'أحمد',
        'familyNameAr' => 'الخطيب',
    ];

    new PersonName(...array_merge($base, $overrides));
})->with([
    'blank English first name' => [['firstNameEn' => '   ']],
    'blank English family name' => [['familyNameEn' => '']],
    'blank Arabic first name' => [['firstNameAr' => '']],
    'blank Arabic family name' => [['familyNameAr' => '   ']],
])->throws(InvalidArgumentException::class);

it('stringifies to the English full name', function () {
    $name = new PersonName(
        firstNameEn: 'Ahmad',
        familyNameEn: 'Al-Khatib',
        firstNameAr: 'أحمد',
        familyNameAr: 'الخطيب',
    );

    expect((string) $name)->toBe('Ahmad Al-Khatib');
});
