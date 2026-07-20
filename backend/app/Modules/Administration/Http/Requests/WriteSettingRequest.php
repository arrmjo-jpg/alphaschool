<?php

namespace App\Modules\Administration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors packages/contracts/src/settings/settings.request.ts's
 * WriteSettingRequestSchema exactly -- `expectedVersion` is mandatory,
 * not optional, matching SettingsResolver::write()'s own
 * optimistic-locking contract (ADR-0018 Decision 8), which has no
 * "just overwrite" path.
 */
class WriteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // SettingsResolver::write() itself is the real permission gate, not this request.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
        ];
    }
}
