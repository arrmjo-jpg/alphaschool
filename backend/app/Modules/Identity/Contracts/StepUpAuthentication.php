<?php

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Contact;

/**
 * Plain login is not sufficient authorization for sensitive actions
 * (registering a new child, changing payment details) --
 * docs/DOMAIN_BLUEPRINT.md §8 requires step-up authentication (OTP to an
 * already-verified contact channel) for those.
 *
 * The mechanism is built now; real delivery (actually sending the code
 * via SMS/email) is wired once the Notifications module exists later
 * this phase. No guardian-registration flow exists yet to protect
 * (Phase 4) -- this contract exists so that flow only needs to call
 * challenge()/verify(), not design the OTP mechanism itself.
 */
interface StepUpAuthentication
{
    /**
     * Issues a challenge to $contact, which must already be verified --
     * step-up auth can only target a channel already proven to belong to
     * this user, never an unverified one. Returns an opaque challenge
     * ID the caller passes back to verify().
     */
    public function challenge(User $user, Contact $contact): string;

    public function verify(User $user, string $challengeId, string $code): bool;
}
