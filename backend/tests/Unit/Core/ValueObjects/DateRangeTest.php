<?php

use App\Core\ValueObjects\DateRange;

it('rejects until equal to from (zero-length range)', function () {
    new DateRange('2026-01-01', '2026-01-01');
})->throws(InvalidArgumentException::class);

it('rejects until before from', function () {
    new DateRange('2026-06-01', '2026-01-01');
})->throws(InvalidArgumentException::class);

it('accepts an open-ended range with a null until', function () {
    $range = new DateRange('2026-01-01');

    expect($range->isOpenEnded())->toBeTrue()
        ->and($range->until)->toBeNull();
});

it('contains a date within the range, excluding the until boundary', function () {
    $range = new DateRange('2026-01-01', '2026-06-01');

    expect($range->contains('2026-01-01'))->toBeTrue()  // from is inclusive
        ->and($range->contains('2026-03-15'))->toBeTrue()
        ->and($range->contains('2026-05-31'))->toBeTrue()
        ->and($range->contains('2026-06-01'))->toBeFalse() // until is exclusive
        ->and($range->contains('2025-12-31'))->toBeFalse();
});

it('treats an open-ended range as containing any date on or after from', function () {
    $range = new DateRange('2026-01-01');

    expect($range->contains('2026-01-01'))->toBeTrue()
        ->and($range->contains('2099-01-01'))->toBeTrue()
        ->and($range->contains('2025-12-31'))->toBeFalse();
});

it('does not consider adjacent ranges (until == next from) as overlapping', function () {
    $a = new DateRange('2026-01-01', '2026-06-01');
    $b = new DateRange('2026-06-01', '2026-12-01');

    expect($a->overlaps($b))->toBeFalse()
        ->and($b->overlaps($a))->toBeFalse();
});

it('detects a genuine one-day overlap', function () {
    $a = new DateRange('2026-01-01', '2026-06-02');
    $b = new DateRange('2026-06-01', '2026-12-01');

    expect($a->overlaps($b))->toBeTrue()
        ->and($b->overlaps($a))->toBeTrue();
});

it('detects overlap when one range is open-ended and starts before the other ends', function () {
    $ongoing = new DateRange('2026-01-01');
    $bounded = new DateRange('2026-06-01', '2026-12-01');

    expect($ongoing->overlaps($bounded))->toBeTrue()
        ->and($bounded->overlaps($ongoing))->toBeTrue();
});

it('does not detect overlap when a bounded range ended before an open-ended range starts', function () {
    $ongoing = new DateRange('2026-01-01');
    $past = new DateRange('2023-01-01', '2023-06-01');

    expect($ongoing->overlaps($past))->toBeFalse()
        ->and($past->overlaps($ongoing))->toBeFalse();
});

it('detects overlap between two open-ended ranges regardless of which starts later', function () {
    $a = new DateRange('2026-01-01');
    $b = new DateRange('2027-01-01');

    expect($a->overlaps($b))->toBeTrue()
        ->and($b->overlaps($a))->toBeTrue();
});

it('considers an identical range as overlapping itself', function () {
    $a = new DateRange('2026-01-01', '2026-06-01');
    $b = new DateRange('2026-01-01', '2026-06-01');

    expect($a->overlaps($b))->toBeTrue();
});

it('compares equality correctly, including open-ended ranges', function () {
    $a = new DateRange('2026-01-01', '2026-06-01');
    $b = new DateRange('2026-01-01', '2026-06-01');
    $c = new DateRange('2026-01-01');
    $d = new DateRange('2026-01-01');

    expect($a->equals($b))->toBeTrue()
        ->and($c->equals($d))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('formats as a readable half-open interval string', function () {
    expect((string) new DateRange('2026-01-01', '2026-06-01'))->toBe('[2026-01-01, 2026-06-01)')
        ->and((string) new DateRange('2026-01-01'))->toBe('[2026-01-01, ∞)');
});
