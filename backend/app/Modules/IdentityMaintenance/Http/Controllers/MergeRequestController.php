<?php

namespace App\Modules\IdentityMaintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityMaintenance\Http\Requests\CreateMergeRequestRequest;
use App\Modules\IdentityMaintenance\Models\MergeRequest;
use App\Modules\IdentityMaintenance\Services\MergeOrchestrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A functional admin surface only (Sprint 3.2 Scope -- Out: "Merge UI
 * polish beyond a functional admin screen"). Every write action is
 * Policy-gated via Gate::authorize() (registered in AppServiceProvider),
 * which throws AuthorizationException on its own -- no manual checks
 * duplicated here. Step-level approval eligibility is ApprovalEngine's
 * own job, delegated inside MergeOrchestrationService, never
 * re-implemented in this controller either.
 */
class MergeRequestController extends Controller
{
    public function __construct(private readonly MergeOrchestrationService $service) {}

    public function store(CreateMergeRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', MergeRequest::class);

        $mergeRequest = MergeRequest::create([
            ...$request->validated(),
            'requested_by_id' => $request->user()->id,
        ]);

        return response()->json($mergeRequest, 201);
    }

    public function preview(MergeRequest $mergeRequest): JsonResponse
    {
        return response()->json($this->service->preview($mergeRequest));
    }

    public function dryRun(MergeRequest $mergeRequest): JsonResponse
    {
        return response()->json($this->service->dryRun($mergeRequest));
    }

    public function requestApproval(MergeRequest $mergeRequest): JsonResponse
    {
        Gate::authorize('requestApproval', $mergeRequest);

        return response()->json($this->service->requestApproval($mergeRequest));
    }

    public function approve(Request $request, MergeRequest $mergeRequest): JsonResponse
    {
        Gate::authorize('approve', $mergeRequest);

        return response()->json($this->service->approve($mergeRequest, $request->user(), $request->input('comments')));
    }

    public function reject(Request $request, MergeRequest $mergeRequest): JsonResponse
    {
        Gate::authorize('reject', $mergeRequest);

        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];

        return response()->json($this->service->reject($mergeRequest, $request->user(), $reason));
    }

    public function execute(MergeRequest $mergeRequest): JsonResponse
    {
        return response()->json($this->service->execute($mergeRequest));
    }

    public function requestRollback(MergeRequest $mergeRequest): JsonResponse
    {
        Gate::authorize('rollback', $mergeRequest);

        return response()->json($this->service->requestRollback($mergeRequest));
    }

    public function approveRollback(Request $request, MergeRequest $mergeRequest): JsonResponse
    {
        Gate::authorize('approve', $mergeRequest);

        return response()->json($this->service->approveRollback($mergeRequest, $request->user(), $request->input('comments')));
    }

    public function rejectRollback(Request $request, MergeRequest $mergeRequest): JsonResponse
    {
        Gate::authorize('reject', $mergeRequest);

        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];

        return response()->json($this->service->rejectRollback($mergeRequest, $request->user(), $reason));
    }

    public function rollback(MergeRequest $mergeRequest): JsonResponse
    {
        return response()->json($this->service->rollback($mergeRequest));
    }
}
