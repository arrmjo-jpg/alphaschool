<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Modules\Academic\Http\Controllers\Concerns\AcademicMasterDataController;
use App\Modules\Academic\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * UI Sprint 1-B's fifth and final entity (docs/ADMIN_DESIGN_SYSTEM.md
 * §28.17) -- the hardest test of the pattern's genericity: a
 * non-bilingual name (like §28.6 already anticipated for Section
 * specifically) and three real FKs (branch_id, academic_year_id,
 * grade_level_id), still expressed as one thin subclass over the
 * shared base with zero changes to entity-workspace/'s shared shells.
 */
class SectionController extends AcademicMasterDataController
{
    protected function modelClass(): string
    {
        return Section::class;
    }

    protected function searchableFields(): array
    {
        return ['name'];
    }

    protected function statusField(): string
    {
        return 'is_active';
    }

    protected function statusType(): string
    {
        return 'boolean';
    }

    protected function applyExtraFilters(Builder $query, Request $request): void
    {
        if ($academicYearId = $request->query('academic_year_id')) {
            $query->where('academic_year_id', $academicYearId);
        }
    }

    protected function validationRules(bool $isUpdate, int|string|null $id): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')->ignore($id)->where(fn ($q) => $q
                    ->where('branch_id', request('branch_id'))
                    ->where('academic_year_id', request('academic_year_id'))
                    ->where('grade_level_id', request('grade_level_id'))),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
