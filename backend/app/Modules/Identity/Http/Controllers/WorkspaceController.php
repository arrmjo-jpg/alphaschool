<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\WorkspaceAccessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/ADMIN_PLATFORM.md's one required backend surface: which
 * workspace definitions the current user can access, computed
 * server-side. See WorkspaceAccessResolver for why this returns an
 * empty list today.
 */
class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceAccessResolver $resolver) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'workspaces' => $this->resolver->resolve($request->user()),
        ]);
    }
}
