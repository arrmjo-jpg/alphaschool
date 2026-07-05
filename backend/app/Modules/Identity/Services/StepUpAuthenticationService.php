<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Contracts\StepUpAuthentication;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The challenge/verify mechanics are real and tested; actual delivery
 * (SMS/email to the verified contact) is NOT wired yet -- the generated
 * code is stored but never sent anywhere, since the Notifications
 * module this depends on doesn't exist yet (later this phase). Replace
 * the "not sent" gap once it does; the mechanism itself does not need
 * to change.
 */
class StepUpAuthenticationService implements StepUpAuthentication
{
    private const CACHE_PREFIX = 'step_up_challenge:';

    private const TTL_MINUTES = 5;

    public function challenge(User $user, Contact $contact): string
    {
        if ($contact->person_id !== $user->person_id) {
            throw new InvalidArgumentException('Step-up authentication requires a contact belonging to the authenticating user.');
        }

        if (! $contact->isVerified()) {
            throw new InvalidArgumentException('Step-up authentication requires an already-verified contact.');
        }

        $challengeId = (string) Str::uuid();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$challengeId, [
            'user_id' => $user->id,
            'code' => $code,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $challengeId;
    }

    public function verify(User $user, string $challengeId, string $code): bool
    {
        $stored = Cache::get(self::CACHE_PREFIX.$challengeId);

        if ($stored === null || $stored['user_id'] !== $user->id) {
            return false;
        }

        $valid = hash_equals($stored['code'], $code);

        if ($valid) {
            Cache::forget(self::CACHE_PREFIX.$challengeId);
        }

        return $valid;
    }
}
