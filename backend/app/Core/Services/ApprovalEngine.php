<?php

namespace App\Core\Services;

use App\Core\Models\ApprovalRequest;
use App\Core\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * A generic, sequential multi-step approval workflow
 * (docs/DOMAIN_BLUEPRINT.md §6/§13). Deliberately shallow: this class
 * knows nothing about what it's approving (a Merge, a Leave Request, a
 * Scholarship grant) -- only how to route a request through an ordered
 * chain of steps and record decisions. Domain knowledge about WHO should
 * approve WHAT stays with the calling module.
 */
class ApprovalEngine
{
    /**
     * @param  array<int, array{required_role?: string, required_user_id?: int}>  $steps  Ordered list of step definitions. Each must specify required_role and/or required_user_id.
     */
    public function request(
        Model $requestable,
        array $steps,
        Model $requestedBy,
        ?string $reason = null,
        bool $disallowRequesterAsApprover = true,
    ): ApprovalRequest {
        if (empty($steps)) {
            throw new InvalidArgumentException('ApprovalEngine: an approval request needs at least one step.');
        }

        foreach ($steps as $i => $step) {
            if (empty($step['required_role']) && empty($step['required_user_id'])) {
                throw new InvalidArgumentException(
                    'ApprovalEngine: step '.($i + 1).' must specify a required_role and/or a required_user_id.',
                );
            }
        }

        return DB::transaction(function () use ($requestable, $steps, $requestedBy, $reason, $disallowRequesterAsApprover) {
            $request = ApprovalRequest::create([
                'requestable_type' => $requestable->getMorphClass(),
                'requestable_id' => $requestable->getKey(),
                'status' => 'pending',
                'requested_by_id' => $requestedBy->getKey(),
                'reason' => $reason,
                'disallow_requester_as_approver' => $disallowRequesterAsApprover,
                'current_step_number' => 1,
            ]);

            foreach (array_values($steps) as $i => $step) {
                ApprovalStep::create([
                    'approval_request_id' => $request->id,
                    'step_number' => $i + 1,
                    'required_role' => $step['required_role'] ?? null,
                    'required_user_id' => $step['required_user_id'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return $request->fresh('steps');
        });
    }

    public function approve(ApprovalRequest $request, Model $approver, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $comments) {
            $request->refresh();
            $this->assertPending($request);
            $step = $this->currentStepOrFail($request);
            $this->assertEligible($request, $step, $approver);

            $step->update([
                'status' => 'approved',
                'decided_by_id' => $approver->getKey(),
                'decided_at' => now(),
                'comments' => $comments,
            ]);

            $nextStep = $request->steps()->where('step_number', $request->current_step_number + 1)->first();

            if ($nextStep !== null) {
                $request->update(['current_step_number' => $nextStep->step_number]);
            } else {
                $request->update(['status' => 'approved', 'decided_at' => now()]);
            }

            return $request->fresh('steps');
        });
    }

    public function reject(ApprovalRequest $request, Model $approver, string $reason): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $reason) {
            $request->refresh();
            $this->assertPending($request);
            $step = $this->currentStepOrFail($request);
            $this->assertEligible($request, $step, $approver);

            $step->update([
                'status' => 'rejected',
                'decided_by_id' => $approver->getKey(),
                'decided_at' => now(),
                'comments' => $reason,
            ]);

            // Rejecting any single step rejects the whole chain -- a
            // sequential approval is an all-or-nothing chain, not a
            // majority vote.
            $request->update(['status' => 'rejected', 'decided_at' => now()]);

            return $request->fresh('steps');
        });
    }

    public function cancel(ApprovalRequest $request, Model $cancelledBy, string $reason): ApprovalRequest
    {
        $this->assertPending($request);

        $request->update([
            'status' => 'cancelled',
            'decided_at' => now(),
            'reason' => trim($request->reason.' | Cancelled by #'.$cancelledBy->getKey().': '.$reason, ' |'),
        ]);

        return $request->fresh('steps');
    }

    protected function assertPending(ApprovalRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException("ApprovalRequest #{$request->id} is already '{$request->status}' -- cannot act on a non-pending request.");
        }
    }

    protected function currentStepOrFail(ApprovalRequest $request): ApprovalStep
    {
        $step = $request->currentStep();

        if ($step === null) {
            throw new RuntimeException("ApprovalRequest #{$request->id} has no step #{$request->current_step_number} -- data integrity issue.");
        }

        return $step;
    }

    protected function assertEligible(ApprovalRequest $request, ApprovalStep $step, Model $approver): void
    {
        if ($request->disallow_requester_as_approver && $approver->getKey() === $request->requested_by_id) {
            throw new RuntimeException(
                "ApprovalRequest #{$request->id}: the requester may not approve their own request.",
            );
        }

        $eligible = false;

        if ($step->required_user_id !== null) {
            $eligible = $eligible || $approver->getKey() === $step->required_user_id;
        }

        if ($step->required_role !== null && method_exists($approver, 'hasRole')) {
            // Duck-typed on purpose -- Core does not import Spatie's
            // package classes directly, it only relies on the hasRole()
            // convention already adopted system-wide, so this stays
            // testable with plain models that have no roles at all.
            $eligible = $eligible || $approver->hasRole($step->required_role);
        }

        if (! $eligible) {
            throw new RuntimeException(
                "User #{$approver->getKey()} is not eligible to decide step #{$step->step_number} of ApprovalRequest #{$request->id}.",
            );
        }
    }
}
