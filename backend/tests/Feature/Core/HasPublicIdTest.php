<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Fixtures\PublicIdFixture;

beforeEach(function () {
    Schema::create('public_id_fixtures', function (Blueprint $table) {
        $table->id();
        $table->ulid('public_id')->unique();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('public_id_fixtures');
});

it('generates a ulid public_id automatically on creation', function () {
    $model = PublicIdFixture::create(['name' => 'test']);

    expect($model->public_id)->not->toBeNull()
        ->and(Str::isUlid($model->public_id))->toBeTrue();
});

it('never generates duplicate public_ids across records', function () {
    $first = PublicIdFixture::create(['name' => 'one']);
    $second = PublicIdFixture::create(['name' => 'two']);

    expect($first->public_id)->not->toBe($second->public_id);
});

it('does not overwrite an explicitly provided public_id', function () {
    $explicit = (string) Str::ulid();

    $model = PublicIdFixture::create(['name' => 'test', 'public_id' => $explicit]);

    expect($model->public_id)->toBe($explicit);
});

it('uses public_id as the route key, never the internal integer id', function () {
    $model = PublicIdFixture::create(['name' => 'test']);

    expect($model->getRouteKeyName())->toBe('public_id')
        ->and($model->getRouteKey())->toBe($model->public_id);
});
