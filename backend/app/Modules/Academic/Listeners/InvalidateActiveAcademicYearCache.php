<?php

namespace App\Modules\Academic\Listeners;

use App\Modules\Academic\Events\AcademicYearClosed;
use App\Modules\Academic\Services\AcademicCatalogService;

/**
 * Keeps AcademicCatalogService's cached active year in sync with
 * AcademicYear::close() without coupling the model to the service layer
 * (Sprint 4.1 Technical Specification, §14 -- "not solved at the model
 * layer"). Registered explicitly in AppServiceProvider::boot(), the
 * same reason Gate::policy() bindings are explicit there: this class
 * lives under App\Modules\Academic\Listeners, outside Laravel's default
 * app/Listeners auto-discovery path.
 */
class InvalidateActiveAcademicYearCache
{
    public function __construct(private readonly AcademicCatalogService $academicCatalogService) {}

    public function handle(AcademicYearClosed $event): void
    {
        $this->academicCatalogService->forgetActiveYearCache();
    }
}
