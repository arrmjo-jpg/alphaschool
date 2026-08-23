<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Modules\Academic\Http\Controllers\Concerns\AcademicMasterDataController;
use App\Modules\Academic\Models\GradeLevel;
use Illuminate\Validation\Rule;

/**
 * UI Sprint 1-B's second entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * exercises the shared base's boolean-status branch for the first
 * time (AcademicYear was lifecycle3), with zero changes to the base
 * class itself.
 */
class GradeLevelController extends AcademicMasterDataController
{
    protected function modelClass(): string
    {
        return GradeLevel::class;
    }

    protected function searchableFields(): array
    {
        return ['code', 'name_en', 'name_ar'];
    }

    protected function statusField(): string
    {
        return 'is_active';
    }

    protected function statusType(): string
    {
        return 'boolean';
    }

    protected function validationRules(bool $isUpdate, int|string|null $id): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('grade_levels', 'code')->ignore($id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sequence_order' => ['required', 'integer', 'min:1', Rule::unique('grade_levels', 'sequence_order')->ignore($id)],
        ];
    }
}
