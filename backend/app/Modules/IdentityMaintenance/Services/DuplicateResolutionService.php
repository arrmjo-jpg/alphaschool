<?php

namespace App\Modules\IdentityMaintenance\Services;

use App\Core\Services\DuplicateDetectionService;
use App\Core\ValueObjects\DuplicateMatchResult;
use App\Core\ValueObjects\DuplicateSignals;
use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\DuplicateFlag;
use App\Modules\People\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The workflow layer over App\Core\Services\DuplicateDetectionService
 * (Addendum C2): the algorithm stays a domain-agnostic Core service that
 * never persists anything or decides what happens with its output --
 * this class is what turns "these two rows share a search_key prefix"
 * into an actual candidate list to score (the calling module's job, per
 * that service's own docblock), persists the flaggable results, and
 * lets a permitted reviewer resolve each one.
 *
 * Deliberately on-demand only this sprint -- no automatic listener on
 * Person creation exists yet (that would require inventing a new domain
 * event not named anywhere in this sprint's scope). Scanning is
 * triggered by calling scanForCandidates()/flagCandidates() explicitly.
 *
 * "Resolve as merge-candidate" only reclassifies this flag's own status
 * -- it does not create or link to a MergeRequest, which is Sprint
 * 3.2's aggregate, not this sprint's.
 */
class DuplicateResolutionService
{
    public function __construct(private readonly DuplicateDetectionService $detector) {}

    /**
     * Narrows candidates by search_key (the same indexed column
     * DuplicateDetectionService::computeSearchKey() populates on every
     * Person, Sprint 2.1) before scoring -- never a full-table scan.
     *
     * @return DuplicateMatchResult[]
     */
    public function scanForCandidates(Person $probe): array
    {
        $candidates = Person::where('search_key', $probe->search_key)
            ->where('id', '!=', $probe->id)
            ->get();

        return $this->detector->rank(
            $this->toSignals($probe),
            $candidates->map(fn (Person $candidate) => $this->toSignals($candidate)),
        );
    }

    /**
     * Persists a DuplicateFlag per flaggable match. Idempotent by
     * design (firstOrCreate on the unique source/candidate pair) since
     * this may reasonably be invoked more than once against the same
     * probe -- a second scan must not throw on an already-flagged pair.
     *
     * @param  DuplicateMatchResult[]  $matches
     * @return DuplicateFlag[]
     */
    public function flagCandidates(Person $probe, array $matches): array
    {
        return array_map(function (DuplicateMatchResult $match) use ($probe): DuplicateFlag {
            /** @var Person $candidate */
            $candidate = $match->subject;

            return DuplicateFlag::firstOrCreate(
                ['source_person_id' => $probe->id, 'candidate_person_id' => $candidate->id],
                ['score' => $match->score, 'tier' => $match->tier, 'status' => DuplicateFlag::STATUS_PENDING],
            );
        }, $matches);
    }

    public function resolveAsMergeCandidate(DuplicateFlag $flag, User $reviewer, ?string $notes = null): DuplicateFlag
    {
        $this->assertCanReview($reviewer);

        $flag->update([
            'status' => DuplicateFlag::STATUS_MERGE_CANDIDATE,
            'resolved_by_id' => $reviewer->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        return $flag;
    }

    public function dismiss(DuplicateFlag $flag, User $reviewer, ?string $notes = null): DuplicateFlag
    {
        $this->assertCanReview($reviewer);

        $flag->update([
            'status' => DuplicateFlag::STATUS_DISMISSED,
            'resolved_by_id' => $reviewer->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        return $flag;
    }

    /**
     * Uses hasPermissionTo() with an explicit guard, not can(): this
     * app's default auth guard (config/auth.php) is 'web', but every
     * permission here is seeded under 'sanctum' (PermissionSeeder's own
     * GUARD constant, matching this API-only app's real traffic).
     * can() resolves against the default guard and silently misses --
     * found via this sprint's own test, not assumed to work.
     */
    private function assertCanReview(User $reviewer): void
    {
        if (! $reviewer->hasPermissionTo('identity.review-duplicates', 'sanctum')) {
            throw new AuthorizationException(
                'This action requires the identity.review-duplicates permission.'
            );
        }
    }

    private function toSignals(Person $person): DuplicateSignals
    {
        return new DuplicateSignals(
            name: $person->name(),
            dob: $person->dob?->format('Y-m-d'),
            nationality: $person->nationality,
            identityDocuments: $person->identityDocuments->map(
                fn ($document) => $document->toReference()
            )->all(),
            subject: $person,
        );
    }
}
