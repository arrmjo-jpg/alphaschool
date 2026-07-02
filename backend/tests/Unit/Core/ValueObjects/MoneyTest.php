<?php

use App\Core\ValueObjects\Money;

it('rejects a structurally invalid currency code', function () {
    Money::fromMinorUnits(100, 'usd'); // lowercase
})->throws(InvalidArgumentException::class);

it('rejects a currency code that is not 3 letters', function () {
    Money::fromMinorUnits(100, 'US');
})->throws(InvalidArgumentException::class);

it('does not hardcode a whitelist of supported currencies -- any structurally valid code is accepted', function () {
    // Deliberately not a "real" widely-known currency -- proving there is
    // no hidden allow-list, only structural validation.
    $money = Money::fromMinorUnits(500, 'ZZZ');

    expect($money->currency)->toBe('ZZZ');
});

it('converts a 2-decimal currency decimal string to minor units correctly', function () {
    $money = Money::fromDecimalString('12.50', 'USD');

    expect($money->minorUnits)->toBe(1250)
        ->and($money->toDecimalString())->toBe('12.50');
});

it('converts a 3-decimal currency (JOD) decimal string to minor units correctly', function () {
    $money = Money::fromDecimalString('12.500', 'JOD');

    expect($money->minorUnits)->toBe(12500)
        ->and($money->toDecimalString())->toBe('12.500');
});

it('converts a 0-decimal currency (JPY) decimal string to minor units correctly', function () {
    $money = Money::fromDecimalString('1500', 'JPY');

    expect($money->minorUnits)->toBe(1500)
        ->and($money->toDecimalString())->toBe('1500');
});

it('rejects a non-numeric decimal string', function () {
    Money::fromDecimalString('abc', 'USD');
})->throws(InvalidArgumentException::class);

it('adds two amounts in the same currency', function () {
    $a = Money::fromDecimalString('10.00', 'USD');
    $b = Money::fromDecimalString('5.50', 'USD');

    expect($a->add($b)->toDecimalString())->toBe('15.50');
});

it('subtracts two amounts in the same currency', function () {
    $a = Money::fromDecimalString('10.00', 'USD');
    $b = Money::fromDecimalString('3.25', 'USD');

    expect($a->subtract($b)->toDecimalString())->toBe('6.75');
});

it('rejects adding two different currencies', function () {
    $usd = Money::fromDecimalString('10.00', 'USD');
    $jod = Money::fromDecimalString('10.00', 'JOD');

    $usd->add($jod);
})->throws(InvalidArgumentException::class);

it('rejects comparing two different currencies', function () {
    $usd = Money::fromDecimalString('10.00', 'USD');
    $eur = Money::fromDecimalString('10.00', 'EUR');

    $usd->greaterThan($eur);
})->throws(InvalidArgumentException::class);

it('multiplies and rounds half away from zero, documented behavior', function () {
    $amount = Money::fromDecimalString('10.00', 'USD'); // 1000 minor units

    // 1000 * 0.155 = 155.0 exactly -- no rounding ambiguity
    expect($amount->multiply('0.155')->minorUnits)->toBe(155);

    // 1000 * 0.1005 = 100.5 -- rounds away from zero to 101
    $amount2 = Money::fromDecimalString('10.00', 'USD');
    expect($amount2->multiply('0.1005')->minorUnits)->toBe(101);
});

it('reports zero and negative correctly', function () {
    expect(Money::zero('USD')->isZero())->toBeTrue()
        ->and(Money::fromDecimalString('-5.00', 'USD')->isNegative())->toBeTrue()
        ->and(Money::fromDecimalString('5.00', 'USD')->isNegative())->toBeFalse();
});

it('compares equality by minor units and currency together', function () {
    $a = Money::fromDecimalString('10.00', 'USD');
    $b = Money::fromMinorUnits(1000, 'USD');
    $c = Money::fromMinorUnits(1000, 'EUR');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('formats as a readable string with currency', function () {
    expect((string) Money::fromDecimalString('12.50', 'USD'))->toBe('12.50 USD');
});
