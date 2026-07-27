<?php

namespace App\Modules\Admissions\Services;

use App\Core\Services\DuplicateDetectionService;
use App\Core\ValueObjects\DuplicateSignals;
use App\Core\ValueObjects\IdentityDocumentReference;
use App\Core\ValueObjects\PersonName;
use App\Modules\Admissions\Exceptions\RootOfTrustDocumentRequiredException;
use App\Modules\Admissions\Exceptions\StepUpVerificationFailedException;
use App\Modules\Identity\Contracts\StepUpAuthentication;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Contact;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonIdentityDocument;

/**
 * Orchestrates the two mechanics IMPLEMENTATION_PLAYBOOK.md's Sprint 4.2
 * stub names for the sensitive "submit application" action -- guardian
 * root-of-trust verification and step-up authentication -- by directly
 * reusing the services Phases 2/3 already built (StepUpAuthentication,
 * DuplicateDetectionService), never reimplementing either.
 *
 * Implementation-shape decision, not a redesign: StepUpAuthentication's
 * real, already-built signature (challenge(User, Contact)) requires an
 * authenticated User whose own Contact is already verified -- there is
 * no other way to call it. This means "submit application" necessarily
 * requires the submitting guardian to already be a logged-in User by
 * the time this service runs; that login/registration flow is Identity/
 * People's own, already-shipped concern (Phases 2-3), not something
 * this sprint builds. Duplicate-detection's own named exercise point
 * (IMPLEMENTATION_PLAYBOOK.md: "a returning guardian's new application
 * must correctly find their existing Person/Guardian record") is
 * therefore read as applying to the APPLYING CHILD, not the guardian --
 * an authenticated guardian's own identity is already exact, never
 * fuzzy, so resolveGuardian() below is a plain lookup, while
 * resolveApplicantPerson() is where DuplicateResolutionService's own
 * narrowing-by-search_key + rank() pattern is actually exercised,
 * adapted for a not-yet-persisted probe.
 */
class GuardianVerificationService
{
    public function __construct(
        private readonly StepUpAuthentication $stepUpAuthentication,
        private readonly DuplicateDetectionService $duplicateDetectionService,
    ) {}

    /**
     * Returns the authenticated guardian's own Guardian context row,
     * creating one only if this is genuinely their first child --
     * root-of-trust document evidence is required in that case only, a
     * returning guardian is never re-verified.
     *
     * @param  IdentityDocumentReference[]  $identityDocuments
     */
    public function resolveGuardian(User $user, array $identityDocuments = []): Guardian
    {
        $existing = Guardian::where('person_id', $user->person_id)->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($identityDocuments === []) {
            throw new RootOfTrustDocumentRequiredException;
        }

        foreach ($identityDocuments as $document) {
            PersonIdentityDocument::create([
                'person_id' => $user->person_id,
                'document_type' => $document->documentType,
                'issuing_country' => $document->issuingCountry,
                'number' => $document->number,
                'is_current' => true,
            ]);
        }

        return Guardian::create([
            'person_id' => $user->person_id,
            'lifecycle_status' => Guardian::STATUS_ACTIVE,
        ]);
    }

    /**
     * Resolves the applying child's own Person record -- reuses an
     * existing one (e.g. a prior withdrawn/rejected application for the
     * same child) when duplicate-detection reaches TIER_CERTAIN, per
     * frozen law's own "Person is never re-created for a returning
     * individual" discipline; otherwise creates a new Person.
     *
     * @param  IdentityDocumentReference[]  $identityDocuments
     */
    public function resolveApplicantPerson(
        PersonName $name,
        ?string $dob,
        ?string $nationality,
        string $gender,
        array $identityDocuments = [],
    ): Person {
        $searchKey = $this->duplicateDetectionService->computeSearchKey($name);

        // Independent Review fix -- toSignals() below accesses
        // ->identityDocuments for every candidate; without this,
        // Person::identityDocuments() lazy-loads once per candidate
        // instead of one query total.
        $candidates = Person::where('search_key', $searchKey)->with('identityDocuments')->get();

        $probe = new DuplicateSignals($name, $dob, $nationality, $identityDocuments);

        $matches = $this->duplicateDetectionService->rank(
            $probe,
            $candidates->map(fn (Person $candidate) => $this->toSignals($candidate)),
        );

        foreach ($matches as $match) {
            if ($match->tier === DuplicateDetectionService::TIER_CERTAIN) {
                /** @var Person $person */
                $person = $match->subject;

                return $person;
            }
        }

        return Person::create([
            'first_name_en' => $name->firstNameEn,
            'second_name_en' => $name->secondNameEn,
            'third_name_en' => $name->thirdNameEn,
            'family_name_en' => $name->familyNameEn,
            'first_name_ar' => $name->firstNameAr,
            'second_name_ar' => $name->secondNameAr,
            'third_name_ar' => $name->thirdNameAr,
            'family_name_ar' => $name->familyNameAr,
            'dob' => $dob,
            'nationality' => $nationality,
            'gender' => $gender,
        ]);
    }

    /**
     * @throws StepUpVerificationFailedException
     */
    public function requireStepUp(User $user, Contact $contact, string $challengeId, string $code): void
    {
        if (! $this->stepUpAuthentication->verify($user, $challengeId, $code)) {
            throw new StepUpVerificationFailedException;
        }
    }

    public function challengeStepUp(User $user, Contact $contact): string
    {
        return $this->stepUpAuthentication->challenge($user, $contact);
    }

    private function toSignals(Person $person): DuplicateSignals
    {
        return new DuplicateSignals(
            name: new PersonName(
                firstNameEn: $person->first_name_en,
                familyNameEn: $person->family_name_en,
                firstNameAr: $person->first_name_ar,
                familyNameAr: $person->family_name_ar,
                secondNameEn: $person->second_name_en,
                secondNameAr: $person->second_name_ar,
                thirdNameEn: $person->third_name_en,
                thirdNameAr: $person->third_name_ar,
            ),
            dob: $person->dob?->format('Y-m-d'),
            nationality: $person->nationality,
            identityDocuments: $person->identityDocuments->map(fn (PersonIdentityDocument $document) => new IdentityDocumentReference(
                documentType: $document->document_type,
                issuingCountry: $document->issuing_country,
                number: $document->number,
            ))->all(),
            subject: $person,
        );
    }
}
