<?php

use App\Modules\Academic\Exceptions\NoActiveAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\BranchGradeLevel;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Services\AcademicCatalogService;
use App\Modules\Identity\Models\Branch;

it('resolves the active Academic Year and caches it', function () {
    $active = AcademicYear::factory()->active()->create();

    $service = app(AcademicCatalogService::class);

    expect($service->activeAcademicYear()->is($active))->toBeTrue();

    // A second call must not re-query -- proven by deleting the row and
    // confirming the cached value is still returned.
    AcademicYear::query()->delete();
    expect($service->activeAcademicYear()->is($active))->toBeTrue();
});

it('invalidates the active-year cache when that year closes', function () {
    $active = AcademicYear::factory()->active()->create();
    $service = app(AcademicCatalogService::class);

    $service->activeAcademicYear();

    $active->close();

    $newActive = AcademicYear::factory()->active()->create();

    expect($service->activeAcademicYear()->is($newActive))->toBeTrue();
});

it('throws NoActiveAcademicYearException when zero years are active', function () {
    AcademicYear::factory()->create();

    $service = app(AcademicCatalogService::class);

    expect(fn () => $service->activeAcademicYear())->toThrow(NoActiveAcademicYearException::class);
});

it('throws NoActiveAcademicYearException when more than one year is active', function () {
    AcademicYear::factory()->active()->create();
    AcademicYear::factory()->active()->create();

    $service = app(AcademicCatalogService::class);

    expect(fn () => $service->activeAcademicYear())->toThrow(NoActiveAcademicYearException::class);
});

it('returns only active grade levels currently offered at the given branch', function () {
    $branch = Branch::factory()->create();
    $offered = GradeLevel::factory()->create(['sequence_order' => 1]);
    $notOffered = GradeLevel::factory()->create(['sequence_order' => 2]);
    $inactiveJoin = GradeLevel::factory()->create(['sequence_order' => 3]);

    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $offered->id, 'is_active' => true]);
    BranchGradeLevel::factory()->create(['branch_id' => $branch->id, 'grade_level_id' => $inactiveJoin->id, 'is_active' => false]);

    $service = app(AcademicCatalogService::class);
    $result = $service->gradeLevelsForBranch($branch->id);

    expect($result->pluck('id')->all())->toBe([$offered->id]);
    expect($result->pluck('id'))->not->toContain($notOffered->id, $inactiveJoin->id);
});
