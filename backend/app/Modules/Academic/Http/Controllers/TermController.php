<?php

namespace App\Modules\Academic\Http\Controllers;

use App\Modules\Academic\Http\Controllers\Concerns\AcademicMasterDataController;
use App\Modules\Academic\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * UI Sprint 1-B's fourth entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * the first real FK-scoped entity (academic_year_id) and the first
 * real consumer of applyExtraFilters(), proving the shared base
 * extends to this shape with one small, named override rather than a
 * bespoke index() reimplementation.
 */
class TermController extends AcademicMasterDataController
{
    protected function modelClass(): string
    {
        return Term::class;
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

    protected function applyExtraFilters(Builder $query, Request $request): void
    {
        if ($academicYearId = $request->query('academic_year_id')) {
            $query->where('academic_year_id', $academicYearId);
        }
    }

    protected function validationRules(bool $isUpdate, int|string|null $id): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sequence_order' => [
                'required', 'integer', 'min:1',
                Rule::unique('terms', 'sequence_order')->ignore($id)->where(fn ($q) => $q->where('academic_year_id', request('academic_year_id'))),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }
}
