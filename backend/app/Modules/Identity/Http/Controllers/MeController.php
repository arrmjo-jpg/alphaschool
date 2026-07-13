<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Admin Platform Foundation's shell needs one authenticated
 * endpoint to resolve "who is this and what can they do" -- see
 * docs/adr/0015-admin-platform-foundation-frontend-architecture.md.
 * Coarse nav-gating data only; real authorization is always each
 * endpoint's own Policy.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $person = $user->person;

        return response()->json([
            'user' => [
                'public_id' => $user->public_id,
                'username' => $user->username,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'name' => $person === null ? null : [
                    'en' => $person->name()->fullNameEn(),
                    'ar' => $person->name()->fullNameAr(),
                ],
            ],
            'permissions' => $user->permissionNamesAcrossBranches(),
        ]);
    }
}
