<?php

use App\Core\Services\ApprovalEngine;
use App\Modules\Identity\Models\User;
use Tests\Fixtures\FakeRoleHolder;

function makeUser(): User
{
    return User::factory()->create();
}

it('rejects creating a request with no steps', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();

    $engine->request($requester, [], $requester);
})->throws(InvalidArgumentException::class);

it('rejects a step definition with neither a required role nor a required user', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();

    $engine->request($requester, [[]], $requester);
})->throws(InvalidArgumentException::class);

it('walks a two-step chain to full approval only once both steps approve', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $approverOne = makeUser();
    $approverTwo = makeUser();
    $subject = makeUser(); // stand-in "thing being approved" -- Core has no other model yet

    $request = $engine->request($subject, [
        ['required_user_id' => $approverOne->id],
        ['required_user_id' => $approverTwo->id],
    ], $requester);

    expect($request->status)->toBe('pending')
        ->and($request->current_step_number)->toBe(1);

    $request = $engine->approve($request, $approverOne, 'looks fine');

    expect($request->status)->toBe('pending') // not fully approved yet -- step 2 remains
        ->and($request->current_step_number)->toBe(2);

    $request = $engine->approve($request, $approverTwo);

    expect($request->status)->toBe('approved')
        ->and($request->decided_at)->not->toBeNull();
});

it('rejecting any single step rejects the whole chain, not just that step', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $approverOne = makeUser();
    $approverTwo = makeUser();
    $subject = makeUser();

    $request = $engine->request($subject, [
        ['required_user_id' => $approverOne->id],
        ['required_user_id' => $approverTwo->id],
    ], $requester);

    $request = $engine->reject($request, $approverOne, 'not appropriate');

    expect($request->status)->toBe('rejected');

    // Step 2's approver should not be able to act on an already-decided request.
    $engine->approve($request, $approverTwo);
})->throws(RuntimeException::class);

it('blocks the requester from approving their own request by default', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $subject = makeUser();

    $request = $engine->request($subject, [
        ['required_user_id' => $requester->id],
    ], $requester);

    $engine->approve($request, $requester);
})->throws(RuntimeException::class);

it('allows self-approval only when explicitly opted out of the restriction', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $subject = makeUser();

    $request = $engine->request($subject, [
        ['required_user_id' => $requester->id],
    ], $requester, disallowRequesterAsApprover: false);

    $request = $engine->approve($request, $requester);

    expect($request->status)->toBe('approved');
});

it('rejects a user who is neither the required user nor holds the required role', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $requiredApprover = makeUser();
    $stranger = makeUser();
    $subject = makeUser();

    $request = $engine->request($subject, [
        ['required_user_id' => $requiredApprover->id],
    ], $requester);

    $engine->approve($request, $stranger);
})->throws(RuntimeException::class);

it('grants eligibility via a duck-typed hasRole() check, without Core depending on Spatie directly', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $subject = makeUser();

    $approver = User::factory()->create();
    $fakeApprover = FakeRoleHolder::find($approver->id)->withFakeRoles(['hr_manager']);

    $request = $engine->request($subject, [
        ['required_role' => 'hr_manager'],
    ], $requester);

    $request = $engine->approve($request, $fakeApprover);

    expect($request->status)->toBe('approved');
});

it('rejects a role-based step when the approver model has no hasRole() at all, rather than crashing', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $subject = makeUser();
    $plainUser = makeUser(); // ordinary User, no hasRole() method defined

    $request = $engine->request($subject, [
        ['required_role' => 'hr_manager'],
    ], $requester);

    $engine->approve($request, $plainUser);
})->throws(RuntimeException::class);

it('cannot act on a request that is already approved, rejected, or cancelled', function () {
    $engine = new ApprovalEngine;
    $requester = makeUser();
    $approver = makeUser();
    $subject = makeUser();

    $request = $engine->request($subject, [
        ['required_user_id' => $approver->id],
    ], $requester);

    $engine->cancel($request, $requester, 'no longer needed');

    $engine->approve($request->fresh(), $approver);
})->throws(RuntimeException::class);
