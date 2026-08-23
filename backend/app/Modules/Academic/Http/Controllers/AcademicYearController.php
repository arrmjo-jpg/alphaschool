<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Modules\Academic\Http\Controllers\Concerns\AcademicMasterDataController;
use App\Modules\Academic\Models\AcademicYear;
use Illuminate\Validation\Rule;

/**
 * UI Sprint 1-B's first real Academic entity adapter (docs/ADMIN_DESIGN_SYSTEM.md
 * §28.17) -- a thin, few-line declaration over the shared
 * AcademicMasterDataController; no business logic lives here.
 */
class AcademicYearController extends AcademicMasterDataController
{
    protected function modelClass(): string
    {
        return AcademicYear::class;
    }

    protected function searchableFields(): array
    {
        return ['name_en', 'name_ar'];
    }

    protected function statusField(): string
    {
        return 'status';
    }

    protected function statusType(): string
    {
        return 'lifecycle3';
    }

    protected function validationRules(bool $isUpdate, int|string|null $id): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255', Rule::unique('academic_years', 'name_en')->ignore($id)],
            'name_ar' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }
}
