<?php

namespace App\Modules\Administration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * §27.5's Edit->Test->Save rule -- deliberately has no `expectedVersion`
 * field, unlike WriteProviderCredentialsRequest: testing never touches
 * the Vault's optimistic-locking write path at all, so there is no
 * version to reconcile against.
 */
class TestProviderCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ProviderRegistryController checks the real edit permission itself before invoking TestsCredentials.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credentials' => ['required', 'array'],
        ];
    }
}
