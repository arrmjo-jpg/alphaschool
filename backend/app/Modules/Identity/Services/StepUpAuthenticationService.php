<?php

namespace App\Modules\Identity\Services;

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Modules\Administration\Services\SettingsResolver;
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
 *
 * Code length and challenge lifetime are resolved through the
 * Configuration Platform (Administration Platform Phase 1's proof
 * consumer, see App\Modules\Identity\Support\IdentityOtpSettings) --
 * the values below are the two keys' own declared defaults, so this
 * retrofit changes zero observable behavior from the prior hardcoded
 * constants; only the source of truth moved.
 */
class StepUpAuthenticationService implements StepUpAuthentication
{
    private const CACHE_PREFIX = 'step_up_challenge:';

    public function __construct(private readonly SettingsResolver $settings) {}

    public function challenge(User $user, Contact $contact): string
    {
        if ($contact->person_id !== $user->person_id) {
            throw new InvalidArgumentException('Step-up authentication requires a contact belonging to the authenticating user.');
        }

        if (! $contact->isVerified()) {
            throw new InvalidArgumentException('Step-up authentication requires an already-verified contact.');
        }

        $codeLength = (int) $this->settings->resolve('identity.otp.code_length', ConfigurationScopeContext::global())->value;
        $lifetimeMinutes = (int) $this->settings->resolve('identity.otp.lifetime_minutes', ConfigurationScopeContext::global())->value;

        $challengeId = (string) Str::uuid();
        $code = str_pad((string) random_int(0, (10 ** $codeLength) - 1), $codeLength, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$challengeId, [
            'user_id' => $user->id,
            'code' => $code,
        ], now()->addMinutes($lifetimeMinutes));

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
