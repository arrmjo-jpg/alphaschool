<?php

use App\Core\Models\ReasonCode as ReasonCodeModel;
use App\Core\ValueObjects\ReasonCode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TemporalFixture;

beforeEach(function () {
    Schema::create('temporal_fixtures', function (Blueprint $table) {
        $table->id();
        $table->string('scope_key');
        $table->date('effective_from');
        $table->date('effective_until')->nullable();
        $table->string('status')->default('active');
        $table->foreignId('reason_code_id')->nullable();
        $table->unsignedBigInteger('ended_by_id')->nullable();
        $table->timestamps();
    });

    ReasonCodeModel::create([
        'context' => 'temporal_fixture',
        'code' => 'test_reason',
        'label' => ['en' => 'Test Reason', 'ar' => 'سبب اختباري'],
        'is_active' => true,
    ]);

    ReasonCodeModel::create([
        'context' => 'temporal_fixture',
        'code' => 'deprecated_reason',
        'label' => ['en' => 'Deprecated', 'ar' => 'قديم'],
        'is_active' => false,
    ]);
});

afterEach(function () {
    Schema::dropIfExists('temporal_fixtures');
});

it('allows creating a record with no competing scope', function () {
    $fixture = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    expect($fixture->exists)->toBeTrue()
        ->and($fixture->range()->isOpenEnded())->toBeTrue();
});

it('rejects a new active record that overlaps an existing active one in the same scope', function () {
    TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-06-01', // still overlaps -- the first is open-ended
        'status' => 'active',
    ]);
})->throws(RuntimeException::class);

it('allows a new record in the same scope once the prior one has ended, adjacent with no gap', function () {
    $first = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $first->closeAssignment(new ReasonCode('test_reason'), '2026-06-01');

    $second = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-06-01', // adjacent, not overlapping -- half-open semantics
        'status' => 'active',
    ]);

    expect($second->exists)->toBeTrue();

    $first->refresh();
    expect($first->status)->toBe('ended')
        ->and($first->effective_until->toDateString())->toBe('2026-06-01')
        ->and($first->reason_code_id)->toBe(ReasonCodeModel::where('code', 'test_reason')->first()->id);
});

it('allows overlapping records in different scopes', function () {
    TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $other = TemporalFixture::create([
        'scope_key' => 'section-b',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    expect($other->exists)->toBeTrue();
});

it('does not let a cancelled record block a new one from occupying the same period', function () {
    $mistaken = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $mistaken->cancelAssignment(new ReasonCode('test_reason'));

    $real = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01', // identical period is fine -- the mistaken one is cancelled
        'status' => 'active',
    ]);

    expect($real->exists)->toBeTrue();

    $mistaken->refresh();
    expect($mistaken->status)->toBe('cancelled');
});

it('rejects closing or cancelling with a reason code that is not registered for the context', function () {
    $fixture = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $fixture->closeAssignment(new ReasonCode('not_a_real_reason'));
})->throws(RuntimeException::class);

it('rejects closing with a reason code that exists but is inactive', function () {
    $fixture = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $fixture->closeAssignment(new ReasonCode('deprecated_reason'));
})->throws(RuntimeException::class);

it('resolves asOf() correctly across a chain of two consecutive periods', function () {
    $first = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);
    $first->closeAssignment(new ReasonCode('test_reason'), '2026-06-01');

    TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-06-01',
        'status' => 'active',
    ]);

    $asOfMarch = TemporalFixture::query()->where('scope_key', 'section-a')->asOf('2026-03-01')->first();
    $asOfAugust = TemporalFixture::query()->where('scope_key', 'section-a')->asOf('2026-08-01')->first();
    $asOfBoundaryDay = TemporalFixture::query()->where('scope_key', 'section-a')->asOf('2026-06-01')->first();

    expect($asOfMarch->id)->toBe($first->id)
        ->and($asOfAugust->id)->not->toBe($first->id)
        ->and($asOfBoundaryDay->id)->not->toBe($first->id); // the boundary day belongs to the new period
});

it('active() scope reflects the current date regardless of the stored status label', function () {
    TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => now()->subYear(),
        'status' => 'scheduled', // status label is administrative only -- date range is authoritative
    ]);

    $active = TemporalFixture::query()->where('scope_key', 'section-a')->active()->first();

    expect($active)->not->toBeNull();
});

it('does not re-validate overlap on an unrelated attribute update', function () {
    $first = TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    // Directly seed a second, technically-overlapping row bypassing the
    // guard (simulating data that predates this rule, or a controlled
    // migration) to prove ordinary unrelated updates on the first row
    // don't retroactively re-trigger overlap validation against it.
    TemporalFixture::withoutEvents(fn () => TemporalFixture::create([
        'scope_key' => 'section-a',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]));

    $first->ended_by_id = 999;
    $first->save();

    expect($first->ended_by_id)->toBe(999);
});
