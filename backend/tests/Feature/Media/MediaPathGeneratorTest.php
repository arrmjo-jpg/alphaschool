<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\GlobalMediaFixture;
use Tests\Fixtures\MediaFixture;

beforeEach(function () {
    Schema::create('media_fixtures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->timestamps();
    });

    Storage::fake('public');
    Storage::fake('private');
    Storage::fake('temporary');

    $this->tempUploadPath = tempnam(sys_get_temp_dir(), 'media-test-');
    file_put_contents($this->tempUploadPath, 'test file content');
});

afterEach(function () {
    Schema::dropIfExists('media_fixtures');
    @unlink($this->tempUploadPath);
});

it('produces the correct physical path for a branch-scoped entity on the public disk', function () {
    $fixture = MediaFixture::create(['branch_id' => 7]);

    $media = $fixture->addMedia($this->tempUploadPath)
        ->preservingOriginal()
        ->toMediaCollection('photo', 'public');

    expect($media->getPathRelativeToRoot())
        ->toBe("public/7/MediaFixture/{$fixture->id}/photo/{$media->id}-{$media->file_name}");
});

it('produces the correct physical path on the private disk', function () {
    $fixture = MediaFixture::create(['branch_id' => 3]);

    $media = $fixture->addMedia($this->tempUploadPath)
        ->preservingOriginal()
        ->toMediaCollection('documents', 'private');

    expect($media->disk)->toBe('private')
        ->and($media->getPathRelativeToRoot())
        ->toBe("private/3/MediaFixture/{$fixture->id}/documents/{$media->id}-{$media->file_name}");
});

it('produces the correct physical path on the temporary disk', function () {
    $fixture = MediaFixture::create(['branch_id' => 1]);

    $media = $fixture->addMedia($this->tempUploadPath)
        ->preservingOriginal()
        ->toMediaCollection('staging', 'temporary');

    expect($media->disk)->toBe('temporary')
        ->and($media->getPathRelativeToRoot())
        ->toBe("temporary/1/MediaFixture/{$fixture->id}/staging/{$media->id}-{$media->file_name}");
});

it('omits the branch segment entirely for a global entity, not an empty placeholder', function () {
    $fixture = GlobalMediaFixture::create();

    $media = $fixture->addMedia($this->tempUploadPath)
        ->preservingOriginal()
        ->toMediaCollection('documents', 'public');

    expect($media->getPathRelativeToRoot())
        ->toBe("public/GlobalMediaFixture/{$fixture->id}/documents/{$media->id}-{$media->file_name}")
        ->and($media->getPathRelativeToRoot())->not->toContain('//'); // no doubled slash from an empty branch segment
});

it('omits the branch segment for a branch-scoped model whose current branch_id is null', function () {
    $fixture = MediaFixture::create(['branch_id' => null]);

    $media = $fixture->addMedia($this->tempUploadPath)
        ->preservingOriginal()
        ->toMediaCollection('documents', 'public');

    expect($media->getPathRelativeToRoot())
        ->toBe("public/MediaFixture/{$fixture->id}/documents/{$media->id}-{$media->file_name}");
});
