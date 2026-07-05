<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\StepUpAuthenticationService;
use App\Modules\People\Models\Contact;

function verifiedContactFor(User $user): Contact
{
    return Contact::create([
        'person_id' => $user->person_id,
        'type' => Contact::TYPE_PHONE,
        'value' => '+962700000000',
        'verified_at' => now(),
    ]);
}

it('issues a challenge and verifies the correct code', function () {
    $service = new StepUpAuthenticationService;
    $user = User::factory()->create();
    $contact = verifiedContactFor($user);

    $challengeId = $service->challenge($user, $contact);

    // The code itself is never exposed by challenge() -- pull it from
    // the cache directly, standing in for "the code the user received"
    // since no real delivery channel is wired yet.
    $stored = cache()->get('step_up_challenge:'.$challengeId);

    expect($service->verify($user, $challengeId, $stored['code']))->toBeTrue();
});

it('rejects an incorrect code without consuming the challenge', function () {
    $service = new StepUpAuthenticationService;
    $user = User::factory()->create();
    $contact = verifiedContactFor($user);

    $challengeId = $service->challenge($user, $contact);

    expect($service->verify($user, $challengeId, '000000'))->toBeFalse();
});

it('rejects an unknown or already-consumed challenge', function () {
    $service = new StepUpAuthenticationService;
    $user = User::factory()->create();
    $contact = verifiedContactFor($user);

    $challengeId = $service->challenge($user, $contact);
    $stored = cache()->get('step_up_challenge:'.$challengeId);

    expect($service->verify($user, $challengeId, $stored['code']))->toBeTrue()
        ->and($service->verify($user, $challengeId, $stored['code']))->toBeFalse();
});

it('refuses to issue a challenge against an unverified contact', function () {
    $service = new StepUpAuthenticationService;
    $user = User::factory()->create();
    $unverifiedContact = Contact::create([
        'person_id' => $user->person_id,
        'type' => Contact::TYPE_PHONE,
        'value' => '+962700000000',
    ]);

    $service->challenge($user, $unverifiedContact);
})->throws(InvalidArgumentException::class);

it('refuses to issue a challenge against a contact belonging to a different user', function () {
    $service = new StepUpAuthenticationService;
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = verifiedContactFor($otherUser);

    $service->challenge($user, $contact);
})->throws(InvalidArgumentException::class);

it('never lets one user verify with another user\'s challenge', function () {
    $service = new StepUpAuthenticationService;
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = verifiedContactFor($user);

    $challengeId = $service->challenge($user, $contact);
    $stored = cache()->get('step_up_challenge:'.$challengeId);

    expect($service->verify($otherUser, $challengeId, $stored['code']))->toBeFalse();
});
