<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token-based Sanctum authentication (docs/DOMAIN_BLUEPRINT.md §8) --
 * API tokens for both the React admin app and the Next.js portal, not
 * cookie/session-based SPA auth, since the two consumers may not share
 * a top-level domain.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('username', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'login' => ["This account is {$user->status} and cannot sign in."],
            ]);
        }

        $user->markLoggedIn();

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => [
                'public_id' => $user->public_id,
                'username' => $user->username,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
