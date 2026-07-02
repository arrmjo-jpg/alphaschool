<?php

use App\Core\Models\ReasonCode;
use App\Core\Rules\ValidReasonCode;
use Illuminate\Support\Facades\Validator;

it('passes for a code that is registered and active in the given context', function () {
    ReasonCode::create([
        'context' => 'test_context',
        'code' => 'promoted',
        'label' => ['en' => 'Promoted', 'ar' => 'ترقية'],
        'is_active' => true,
    ]);

    $validator = Validator::make(
        ['reason' => 'promoted'],
        ['reason' => [new ValidReasonCode('test_context')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails for a code that does not exist for the given context', function () {
    $validator = Validator::make(
        ['reason' => 'nonexistent'],
        ['reason' => [new ValidReasonCode('test_context')]],
    );

    expect($validator->fails())->toBeTrue();
});

it('fails for a code that exists but only in a different context', function () {
    ReasonCode::create([
        'context' => 'other_context',
        'code' => 'promoted',
        'label' => ['en' => 'Promoted', 'ar' => 'ترقية'],
        'is_active' => true,
    ]);

    $validator = Validator::make(
        ['reason' => 'promoted'],
        ['reason' => [new ValidReasonCode('test_context')]],
    );

    expect($validator->fails())->toBeTrue();
});

it('fails for a code that exists but is inactive', function () {
    ReasonCode::create([
        'context' => 'test_context',
        'code' => 'deprecated_reason',
        'label' => ['en' => 'Deprecated', 'ar' => 'قديم'],
        'is_active' => false,
    ]);

    $validator = Validator::make(
        ['reason' => 'deprecated_reason'],
        ['reason' => [new ValidReasonCode('test_context')]],
    );

    expect($validator->fails())->toBeTrue();
});
