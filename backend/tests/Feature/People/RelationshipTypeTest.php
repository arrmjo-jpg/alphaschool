<?php

use App\Modules\People\Models\RelationshipType;
use Database\Seeders\RelationshipTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

it('seeds the paternal and maternal kinship terms as genuinely separate rows', function () {
    $this->seed(RelationshipTypeSeeder::class);

    $paternalUncle = RelationshipType::whereCode('uncle_paternal')->firstOrFail();
    $maternalUncle = RelationshipType::whereCode('uncle_maternal')->firstOrFail();
    $paternalGrandfather = RelationshipType::whereCode('grandfather_paternal')->firstOrFail();
    $maternalGrandfather = RelationshipType::whereCode('grandfather_maternal')->firstOrFail();

    App::setLocale('ar');

    expect($paternalUncle->name)->toBe('عم')
        ->and($maternalUncle->name)->toBe('خال')
        ->and($paternalUncle->name)->not->toBe($maternalUncle->name)
        ->and($paternalGrandfather->name)->toBe('جد لأب')
        ->and($maternalGrandfather->name)->toBe('جد لأم')
        ->and($paternalGrandfather->name)->not->toBe($maternalGrandfather->name);
});

it('restricts vocabulary by scope', function () {
    RelationshipType::factory()->guardianStudent()->create(['code' => 'father']);
    RelationshipType::factory()->create(['code' => 'sibling']); // defaults to person_relationship scope

    $guardianStudentTypes = RelationshipType::ofScope(RelationshipType::SCOPE_GUARDIAN_STUDENT)->pluck('code');
    $personRelationshipTypes = RelationshipType::ofScope(RelationshipType::SCOPE_PERSON_RELATIONSHIP)->pluck('code');

    expect($guardianStudentTypes)->toContain('father')
        ->and($guardianStudentTypes)->not->toContain('sibling')
        ->and($personRelationshipTypes)->toContain('sibling')
        ->and($personRelationshipTypes)->not->toContain('father');
});

it('rejects changing code once set', function () {
    $type = RelationshipType::factory()->create(['code' => 'sibling']);

    $type->code = 'sibling_renamed';

    expect(fn () => $type->save())->toThrow(InvalidArgumentException::class);
});

it('deactivates instead of deleting, so historical references remain valid', function () {
    $type = RelationshipType::factory()->create(['code' => 'sibling', 'is_active' => true]);

    $type->update(['is_active' => false]);

    expect(RelationshipType::whereCode('sibling')->exists())->toBeTrue()
        ->and(RelationshipType::active()->whereCode('sibling')->exists())->toBeFalse()
        ->and($type->fresh()->is_active)->toBeFalse();
});

it('refuses to physically delete a relationship type', function () {
    $type = RelationshipType::factory()->create(['code' => 'sibling']);

    expect(fn () => $type->delete())->toThrow(RuntimeException::class);

    expect(RelationshipType::whereCode('sibling')->exists())->toBeTrue();
});
