<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Modules\Academic\Http\Controllers\Concerns\AcademicMasterDataController;
use App\Modules\Academic\Models\Subject;
use Illuminate\Validation\Rule;

/**
 * UI Sprint 1-B's third entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * deliberately near-identical to GradeLevelController (code/bilingual
 * name/boolean status, no sequence_order), the agreed checkpoint
 * proving the shared base generalizes to a second boolean-status
 * Reference Data entity with zero structural changes.
 */
class SubjectController extends AcademicMasterDataController
{
    protected function modelClass(): string
    {
        return Subject::class;
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
            'code' => ['required', 'string', 'max:50', Rule::unique('subjects', 'code')->ignore($id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
        ];
    }
}
