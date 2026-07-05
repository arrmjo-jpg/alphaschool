<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('temporary');
});

it('deletes files older than the threshold and leaves fresh files alone', function () {
    $disk = Storage::disk('temporary');
    $disk->put('stale.txt', 'old content');
    $disk->put('fresh.txt', 'new content');

    // Storage::fake() has no real mtime control, so backdate the stale
    // file directly on the underlying local adapter.
    touch($disk->path('stale.txt'), now()->subHours(48)->getTimestamp());

    $this->artisan('media:purge-temporary', ['--hours' => 24])
        ->assertSuccessful();

    $disk->assertMissing('stale.txt');
    $disk->assertExists('fresh.txt');
});

it('does not delete anything in dry-run mode', function () {
    $disk = Storage::disk('temporary');
    $disk->put('stale.txt', 'old content');

    touch($disk->path('stale.txt'), now()->subHours(48)->getTimestamp());

    $this->artisan('media:purge-temporary', ['--hours' => 24, '--dry-run' => true])
        ->assertSuccessful();

    $disk->assertExists('stale.txt');
});

it('reports when there is nothing stale to purge', function () {
    $disk = Storage::disk('temporary');
    $disk->put('fresh.txt', 'new content');

    $this->artisan('media:purge-temporary', ['--hours' => 24])
        ->expectsOutputToContain('No stale temporary files found.')
        ->assertSuccessful();

    $disk->assertExists('fresh.txt');
});
