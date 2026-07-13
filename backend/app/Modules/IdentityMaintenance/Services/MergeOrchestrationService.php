<?php

namespace App\Modules\IdentityMaintenance\Services;

use App\Core\Contracts\VetoesPersonReassignment;
use App\Core\Services\ApprovalEngine;
use App\Core\ValueObjects\ReassignmentImpact;
use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Events\MergeApproved;
use App\Modules\IdentityMaintenance\Events\MergeDryRunFailed;
use App\Modules\IdentityMaintenance\Events\MergeDryRunPassed;
use App\Modules\IdentityMaintenance\Events\MergeExecuted;
use App\Modules\IdentityMaintenance\Events\MergeRejected;
use App\Modules\IdentityMaintenance\Events\MergeRequested;
use App\Modules\IdentityMaintenance\Events\MergeRolledBack;
use App\Modules\IdentityMaintenance\Models\DuplicateFlag;
use App\Modules\IdentityMaintenance\Models\MergeReassignmentLog;
use App\Modules\IdentityMaintenance\Models\MergeRequest;
use App\Modules\IdentityMaintenance\Support\ApprovalRoutingResolver;
use App\Modules\IdentityMaintenance\Support\MergeFieldResolver;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonRelationship;
use App\Modules\People\Models\Student;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The only place in the system that knows the full registry of
 * ReassignsIdentityReferences implementers (Addendum C4/C7-C10, Sprint
 * 3.2) -- built as a real, explicit registry from day one, per the
 * Playbook's own named risk for this sprint ("don't under-build the
 * orchestration because Academic/HR/Finance don't exist yet").
 *
 * Does NOT include OwnedByAggregate implementers (Contact, Address,
 * PersonIdentityDocument, MergeReassignmentLog) -- those are cascaded
 * by their aggregate root, already in this list.
 */
class MergeOrchestrationService
{
    /**
     * @var class-string[]
     */
    private const REGISTERED_CLASSES = [
        Person::class,
        Employee::class,
        Student::class,
        Guardian::class,
        User::class,
        GuardianStudent::class,
        PersonRelationship::class,
        DuplicateFlag::class,
        MergeRequest::class,
    ];

    private const APPROVE_MERGE_PERMISSION = 'identity.approve-merge';

    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
        private readonly ApprovalRoutingResolver $routingResolver,
        private readonly MergeFieldResolver $fieldResolver,
        private readonly RollbackSafetyChecker $safetyChecker,
    ) {}

    /**
     * Read-only, informational -- no validation, no state change.
     *
     * @return ReassignmentImpact[]
     */
    public function preview(MergeRequest $mergeRequest): array
    {
        $impacts = [];

        foreach (self::REGISTERED_CLASSES as $class) {
            $impacts = array_merge(
                $impacts,
                (new $class)->previewReassignment($mergeRequest->losing_person_id, $mergeRequest->winning_person_id),
            );
        }

        return $impacts;
    }

    /**
     * Calls every implementer's dryRun self-check plus every
     * VetoesPersonReassignment veto (Addendum C9) -- Identity
     * Maintenance enforces the check exists and blocks on failure; the
     * actual structural-validity knowledge lives in each implementer.
     *
     * @return array{passed: bool, conflicts: string[]}
     */
    public function dryRun(MergeRequest $mergeRequest): array
    {
        $conflicts = [];

        foreach (self::REGISTERED_CLASSES as $class) {
            try {
                (new $class)->reassignPerson($mergeRequest->losing_person_id, $mergeRequest->winning_person_id, dryRun: true);
            } catch (Throwable $e) {
                $conflicts[] = $e->getMessage();
            }

            if (is_a($class, VetoesPersonReassignment::class, true)) {
                $veto = (new $class)->canReassignPerson($mergeRequest->losing_person_id);

                if ($veto !== null) {
                    $conflicts[] = $veto;
                }
            }
        }

        $passed = $conflicts === [];

        $mergeRequest->update([
            'last_dry_run_result' => $conflicts,
            'last_dry_run_at' => now(),
        ]);

        if ($passed) {
            $mergeRequest->update(['status' => MergeRequest::STATUS_DRY_RUN_PASSED]);
            MergeDryRunPassed::dispatch($mergeRequest->fresh());
        } else {
            MergeDryRunFailed::dispatch($mergeRequest->fresh(), $conflicts);
        }

        return ['passed' => $passed, 'conflicts' => $conflicts];
    }

    public function requestApproval(MergeRequest $mergeRequest): MergeRequest
    {
        if ($mergeRequest->status !== MergeRequest::STATUS_DRY_RUN_PASSED) {
            throw new RuntimeException("MergeRequest #{$mergeRequest->id} must pass dry run before approval can be requested.");
        }

        $steps = $this->routingResolver->resolveSteps(self::APPROVE_MERGE_PERMISSION);

        $approvalRequest = $this->approvalEngine->request(
            $mergeRequest,
            $steps,
            $mergeRequest->requestedBy,
            'Person merge approval.',
        );

        $mergeRequest->update([
            'status' => MergeRequest::STATUS_PENDING_APPROVAL,
            'approval_request_id' => $approvalRequest->id,
        ]);

        MergeRequested::dispatch($mergeRequest->fresh());

        return $mergeRequest->fresh();
    }

    public function approve(MergeRequest $mergeRequest, User $approver, ?string $comments = null): MergeRequest
    {
        $approvalRequest = $this->approvalEngine->approve($mergeRequest->approvalRequest, $approver, $comments);

        if ($approvalRequest->status === 'approved') {
            $mergeRequest->update(['status' => MergeRequest::STATUS_APPROVED, 'decided_at' => now()]);
            MergeApproved::dispatch($mergeRequest->fresh());
        }

        return $mergeRequest->fresh();
    }

    public function reject(MergeRequest $mergeRequest, User $approver, string $reason): MergeRequest
    {
        $this->approvalEngine->reject($mergeRequest->approvalRequest, $approver, $reason);

        $mergeRequest->update(['status' => MergeRequest::STATUS_REJECTED, 'decided_at' => now()]);
        MergeRejected::dispatch($mergeRequest->fresh());

        return $mergeRequest->fresh();
    }

    /**
     * Execution locking (Sprint 3.2's final review): a first, short
     * transaction locks the row, verifies status is still `approved`,
     * and flips it to `executing` -- a concurrent second caller's own
     * lock+check sees the already-committed `executing` status and is
     * refused, without needing to hold the lock across the entire
     * (potentially slow) reassignment work.
     *
     * The real work runs in a SEPARATE transaction, deliberately: if it
     * throws, the catch block below must still be able to persist
     * status=failed, which would itself be rolled back if it were
     * nested inside the same failing transaction.
     */
    public function execute(MergeRequest $mergeRequest): MergeRequest
    {
        $locked = DB::transaction(function () use ($mergeRequest) {
            $locked = MergeRequest::where('id', $mergeRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== MergeRequest::STATUS_APPROVED) {
                throw new RuntimeException(
                    "MergeRequest #{$mergeRequest->id} is not approved (currently '{$locked->status}') -- refusing to execute, possibly a concurrent execution attempt."
                );
            }

            $locked->update(['status' => MergeRequest::STATUS_EXECUTING]);

            return $locked;
        });

        try {
            DB::transaction(function () use ($locked) {
                $losingPerson = Person::findOrFail($locked->losing_person_id);
                $winningPerson = Person::findOrFail($locked->winning_person_id);

                $snapshot = $losingPerson->only([
                    'first_name_en', 'first_name_ar', 'second_name_en', 'second_name_ar',
                    'third_name_en', 'third_name_ar', 'family_name_en', 'family_name_ar',
                    'dob', 'gender', 'nationality',
                ]);

                // Merge Strategy (delegated, never decided here).
                $winningPerson->update($this->fieldResolver->resolve($losingPerson, $winningPerson));

                // Log-then-reassign, per registered class, using the
                // same data preview() already computed.
                foreach (self::REGISTERED_CLASSES as $class) {
                    $instance = new $class;

                    foreach ($instance->previewReassignment($locked->losing_person_id, $locked->winning_person_id) as $impact) {
                        foreach ($impact->affectedEntityIds as $entityId) {
                            MergeReassignmentLog::create([
                                'merge_request_id' => $locked->id,
                                'class' => $impact->class,
                                'field' => $impact->field,
                                'entity_id' => $entityId,
                                'old_person_id' => $locked->losing_person_id,
                                'new_person_id' => $locked->winning_person_id,
                            ]);
                        }
                    }

                    $instance->reassignPerson($locked->losing_person_id, $locked->winning_person_id);
                }

                // DuplicateFlag lifecycle: the originating flag (if any)
                // becomes STATUS_MERGED -- every OTHER flag referencing
                // either Person was already handled generically above,
                // by DuplicateFlag::reassignPerson()'s own self-reference
                // exclusion.
                if ($locked->duplicate_flag_id !== null) {
                    DuplicateFlag::where('id', $locked->duplicate_flag_id)->update([
                        'status' => DuplicateFlag::STATUS_MERGED,
                        'resolved_at' => now(),
                    ]);
                }

                $locked->update([
                    'status' => MergeRequest::STATUS_EXECUTED,
                    'executed_at' => now(),
                    'losing_person_snapshot' => $snapshot,
                ]);
            });
        } catch (Throwable $e) {
            $locked->update(['status' => MergeRequest::STATUS_FAILED]);

            throw $e;
        }

        $executed = $locked->fresh();
        MergeExecuted::dispatch($executed);

        return $executed;
    }

    public function requestRollback(MergeRequest $mergeRequest): MergeRequest
    {
        if ($mergeRequest->status !== MergeRequest::STATUS_EXECUTED) {
            throw new RuntimeException("MergeRequest #{$mergeRequest->id} is not executed -- nothing to roll back.");
        }

        $steps = $this->routingResolver->resolveSteps(self::APPROVE_MERGE_PERMISSION);

        $approvalRequest = $this->approvalEngine->request(
            $mergeRequest,
            $steps,
            $mergeRequest->requestedBy,
            'Person merge rollback approval.',
        );

        $mergeRequest->update([
            'status' => MergeRequest::STATUS_ROLLBACK_REQUESTED,
            'rollback_approval_request_id' => $approvalRequest->id,
        ]);

        return $mergeRequest->fresh();
    }

    public function approveRollback(MergeRequest $mergeRequest, User $approver, ?string $comments = null): MergeRequest
    {
        $approvalRequest = $this->approvalEngine->approve($mergeRequest->rollbackApprovalRequest, $approver, $comments);

        if ($approvalRequest->status === 'approved') {
            $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLBACK_APPROVED]);
        }

        return $mergeRequest->fresh();
    }

    public function rejectRollback(MergeRequest $mergeRequest, User $approver, string $reason): MergeRequest
    {
        $this->approvalEngine->reject($mergeRequest->rollbackApprovalRequest, $approver, $reason);

        $mergeRequest->update(['status' => MergeRequest::STATUS_ROLLBACK_REJECTED]);

        return $mergeRequest->fresh();
    }

    /**
     * Same two-phase, execution-locked design as execute(), plus the
     * safety check (Addendum C8) runs BEFORE any lock is even acquired
     * -- a rollback that's unsafe must never partially start.
     */
    public function rollback(MergeRequest $mergeRequest): MergeRequest
    {
        $problems = $this->safetyChecker->check($mergeRequest);

        if ($problems !== []) {
            throw new RuntimeException(
                "MergeRequest #{$mergeRequest->id}: rollback blocked -- ".implode('; ', $problems)
            );
        }

        $locked = DB::transaction(function () use ($mergeRequest) {
            $locked = MergeRequest::where('id', $mergeRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== MergeRequest::STATUS_ROLLBACK_APPROVED) {
                throw new RuntimeException(
                    "MergeRequest #{$mergeRequest->id} is not rollback_approved (currently '{$locked->status}') -- refusing, possibly a concurrent rollback attempt."
                );
            }

            $locked->update(['status' => MergeRequest::STATUS_ROLLING_BACK]);

            return $locked;
        });

        try {
            DB::transaction(function () use ($locked) {
                foreach ($locked->logs as $log) {
                    $table = (new $log->class)->getTable();

                    DB::table($table)->where('id', $log->entity_id)->update([
                        $log->field => $log->old_person_id,
                    ]);
                }

                if ($locked->losing_person_snapshot !== null) {
                    Person::where('id', $locked->losing_person_id)->update($locked->losing_person_snapshot);
                }

                $locked->update([
                    'status' => MergeRequest::STATUS_ROLLED_BACK,
                    'rolled_back_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $locked->update(['status' => MergeRequest::STATUS_ROLLBACK_FAILED]);

            throw $e;
        }

        $rolledBack = $locked->fresh();
        MergeRolledBack::dispatch($rolledBack);

        return $rolledBack;
    }
}
