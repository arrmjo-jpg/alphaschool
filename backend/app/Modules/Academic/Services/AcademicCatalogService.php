<?php

namespace App\Modules\Academic\Services;

use App\Modules\Academic\Exceptions\NoActiveAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Support\AcademicPeriodGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The only legal way a sibling Domain module (Admissions, Sprint 4.2
 * onward; Academic's own future Phase 5 build-out) reads this catalog,
 * per the boundary rule stated in both module READMEs: "Domain modules
 * never import a sibling Domain module directly -- only via events or
 * that module's public service contract." Consumers may read, must
 * never write, across this boundary -- enforced by convention/code
 * review, not the type system, matching every other cross-module
 * boundary in this codebase today (e.g. Person is consumed directly by
 * other modules with no DTO layer).
 */
class AcademicCatalogService
{
    private const ACTIVE_YEAR_CACHE_KEY = 'academic.active-year';

    private const ACTIVE_YEAR_CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly AcademicPeriodGuard $academicPeriodGuard) {}

    /**
     * The single highest-traffic read this sprint produces -- every
     * future Applicant/Enrollment write calls this. Cached with a short
     * TTL (5 minutes): rarely written, safe default given how
     * infrequently the active year changes, not yet exercised by a real
     * caller this sprint so deliberately not over-tuned.
     */
    public function activeAcademicYear(): AcademicYear
    {
        return Cache::remember(self::ACTIVE_YEAR_CACHE_KEY, self::ACTIVE_YEAR_CACHE_TTL_SECONDS, function (): AcademicYear {
            $activeYears = AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->get();

            if ($activeYears->count() !== 1) {
                throw new NoActiveAcademicYearException($activeYears->count());
            }

            return $activeYears->first();
        });
    }

    public function findAcademicYear(string $publicId): AcademicYear
    {
        return AcademicYear::query()->where('public_id', $publicId)->firstOrFail();
    }

    /**
     * @return Collection<int, GradeLevel>
     */
    public function gradeLevelsForBranch(int $branchId): Collection
    {
        return GradeLevel::query()
            ->active()
            ->whereHas('branches', function ($query) use ($branchId): void {
                $query->where('branches.id', $branchId)->where('branch_grade_levels.is_active', true);
            })
            ->orderBy('sequence_order')
            ->get();
    }

    public function assertAcademicYearIsOpen(int $academicYearId): void
    {
        $this->academicPeriodGuard->assertAcademicYearIsOpen($academicYearId);
    }

    /**
     * Term's own sibling to assertAcademicYearIsOpen() -- consumed by
     * SubjectOffering's own creation path. AcademicPeriodGuard is
     * injected here rather than SubjectOffering owning a second,
     * independent instance of the same guard, matching how every other
     * closed-period check in this codebase routes through this service
     * rather than a sibling module reaching into Academic\Support
     * directly (this class's own docblock boundary rule).
     */
    public function assertTermIsOpen(int $termId): void
    {
        $this->academicPeriodGuard->assertTermIsOpen($termId);
    }

    /**
     * Sprint 4.4 -- the sole source of truth for grade progression,
     * consumed by Academic's own promotion/graduation Actions. Never
     * exposed to People: Enrollment/Student accept only the resulting
     * ID, never a GradeLevel instance (ADR-0026's layering rule --
     * Foundation must not depend on Domain).
     *
     * Named *ForBranch* deliberately (Independent Review Finding 1,
     * 2026-07-27) -- an earlier version of this method computed "next
     * grade" globally by sequence_order alone, ignoring
     * BranchGradeLevel entirely. That let Promotion create an
     * Enrollment at a grade the branch doesn't actually offer, and let
     * Graduation be wrongly refused for a student who completed the
     * last grade their own branch offers, just because a higher grade
     * exists at a *different* branch. Scoped through the same
     * BranchGradeLevel join gradeLevelsForBranch() already uses, so
     * "next"/"final" always means "next/final among what this branch
     * offers," never system-wide -- the method name itself is meant to
     * make that unmistakable to a future caller, not just this
     * docblock.
     */
    public function nextGradeLevelForBranch(int $branchId, int $currentGradeLevelId): ?GradeLevel
    {
        $current = GradeLevel::findOrFail($currentGradeLevelId);

        return GradeLevel::query()
            ->active()
            ->whereHas('branches', function ($query) use ($branchId): void {
                $query->where('branches.id', $branchId)->where('branch_grade_levels.is_active', true);
            })
            ->where('sequence_order', '>', $current->sequence_order)
            ->orderBy('sequence_order')
            ->first();
    }

    public function isFinalGradeLevelForBranch(int $branchId, int $gradeLevelId): bool
    {
        return $this->nextGradeLevelForBranch($branchId, $gradeLevelId) === null;
    }

    /**
     * Called by InvalidateActiveAcademicYearCache in response to
     * AcademicYearClosed -- the model itself stays unaware this service
     * or its cache exist, matching Person's own style of not knowing
     * about anything that reads it. Public so a future "activate next
     * year" transition (Phase 5, not built here) can invalidate it too.
     */
    public function forgetActiveYearCache(): void
    {
        Cache::forget(self::ACTIVE_YEAR_CACHE_KEY);
    }
}
