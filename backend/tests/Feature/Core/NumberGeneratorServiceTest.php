<?php

use App\Core\Models\NumberSequence;
use App\Core\Services\NumberGeneratorService;

it('starts a new sequence at 1', function () {
    $service = new NumberGeneratorService;

    expect($service->next('test_code'))->toBe('1');
});

it('increments sequentially with no gaps or duplicates across many calls', function () {
    $service = new NumberGeneratorService;

    $values = [];
    for ($i = 0; $i < 50; $i++) {
        $values[] = $service->next('test_code');
    }

    expect($values)->toBe(array_map('strval', range(1, 50)))
        ->and(array_unique($values))->toHaveCount(50);
});

it('keeps separate sequences fully independent by scope', function () {
    $service = new NumberGeneratorService;

    $service->next('student_number', 'branch', 1);
    $service->next('student_number', 'branch', 1);
    $branchOne = $service->next('student_number', 'branch', 1);

    $branchTwo = $service->next('student_number', 'branch', 2);

    expect($branchOne)->toBe('3')
        ->and($branchTwo)->toBe('1'); // independent sequence, unaffected by branch 1's count
});

it('applies padding and a format pattern', function () {
    NumberSequence::create([
        'code' => 'invoice_number',
        'format_pattern' => 'INV-{number}',
        'padding_length' => 5,
    ]);

    $service = new NumberGeneratorService;

    expect($service->next('invoice_number'))->toBe('INV-00001')
        ->and($service->next('invoice_number'))->toBe('INV-00002');
});

it('resets to 1 when the yearly period changes', function () {
    NumberSequence::create([
        'code' => 'yearly_code',
        'reset_period' => 'yearly',
        'period_key' => '2020', // simulate a stale prior year
        'current_value' => 999,
    ]);

    $service = new NumberGeneratorService;

    // The stored period_key ('2020') differs from the real current year,
    // so this call must reset current_value before incrementing.
    expect($service->next('yearly_code'))->toBe('1');
});

it('does not reset when the period has not changed', function () {
    NumberSequence::create([
        'code' => 'yearly_code',
        'reset_period' => 'yearly',
        'period_key' => now()->format('Y'),
        'current_value' => 5,
    ]);

    $service = new NumberGeneratorService;

    expect($service->next('yearly_code'))->toBe('6');
});

it('treats null and explicit no-scope calls as the same global sequence', function () {
    $service = new NumberGeneratorService;

    $service->next('global_code');
    $second = $service->next('global_code', null, null);

    expect($second)->toBe('2')
        ->and(NumberSequence::where('code', 'global_code')->count())->toBe(1);
});
