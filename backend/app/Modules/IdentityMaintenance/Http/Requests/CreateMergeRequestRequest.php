<?php

namespace App\Modules\IdentityMaintenance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMergeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy-gated in the controller, not here.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'losing_person_id' => ['required', 'integer', 'exists:people,id', 'different:winning_person_id'],
            'winning_person_id' => ['required', 'integer', 'exists:people,id'],
            // Nullable deliberately (Sprint 3.2): a manual/API/import
            // merge request has no DuplicateFlag at all.
            'duplicate_flag_id' => ['nullable', 'integer', Rule::exists('duplicate_flags', 'id')],
        ];
    }
}
