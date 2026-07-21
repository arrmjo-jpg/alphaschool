<?php

namespace App\Modules\Administration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors WriteSettingRequest's own shape and reasoning exactly --
 * `expectedVersion` is mandatory, matching ProviderCredentialVault::write()'s
 * own optimistic-locking contract (ADR-0019 Decision 5, reusing ADR-0018
 * Decision 8's algorithm), which has no "just overwrite" path.
 */
class WriteProviderCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ProviderCredentialVault::write() itself is the real permission gate, not this request.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credentials' => ['required', 'array'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
        ];
    }
}
