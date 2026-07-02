<?php

use App\Core\ValueObjects\ReasonCode;

it('accepts a valid snake_case code', function () {
    $reason = new ReasonCode('branch_transfer');

    expect($reason->code)->toBe('branch_transfer')
        ->and((string) $reason)->toBe('branch_transfer');
});

it('rejects an empty code', function () {
    new ReasonCode('   ');
})->throws(InvalidArgumentException::class);

it('rejects a code with spaces', function () {
    new ReasonCode('branch transfer');
})->throws(InvalidArgumentException::class);

it('rejects a code starting with a digit', function () {
    new ReasonCode('1_promoted');
})->throws(InvalidArgumentException::class);

it('rejects a code with uppercase letters', function () {
    new ReasonCode('Retirement');
})->throws(InvalidArgumentException::class);

it('compares equality by code', function () {
    expect((new ReasonCode('promoted'))->equals(new ReasonCode('promoted')))->toBeTrue()
        ->and((new ReasonCode('promoted'))->equals(new ReasonCode('repeated')))->toBeFalse();
});
