<?php

use App\Models\User;
use App\Modules\Media\Models\Media;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\MediaFixture;

// The single most important test in this sprint: private-tier media must
// be unreachable without authentication, and reachable once authenticated
// -- proving the "no raw signed URLs, always through this authenticated
// route" decision in docs/DOMAIN_BLUEPRINT.md §12 actually holds.
beforeEach(function () {
    Schema::create('media_fixtures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->timestamps();
    });

    Storage::fake('private');

    $tempUploadPath = tempnam(sys_get_temp_dir(), 'media-test-');
    file_put_contents($tempUploadPath, 'confidential test content');

    $fixture = MediaFixture::create(['branch_id' => 1]);

    $this->media = $fixture->addMedia($tempUploadPath)
        ->preservingOriginal()
        ->toMediaCollection('documents', 'private');

    @unlink($tempUploadPath);
});

afterEach(function () {
    Schema::dropIfExists('media_fixtures');
});

it('refuses an unauthenticated request for a private media file', function () {
    $response = $this->getJson(route('media.private.show', $this->media));

    $response->assertUnauthorized();
});

it('serves the private media file to an authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('media.private.show', $this->media));

    $response->assertOk();
});

it('returns 404 for a media id that does not exist, before authorization even runs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/private-files/999999');

    $response->assertNotFound();
});
