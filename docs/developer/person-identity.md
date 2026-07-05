# Person & Identity (Sprint 2.1)

`App\Modules\People\Models\Person` (docs/DOMAIN_BLUEPRINT.md §3/§8) is the identity substrate every future context aggregate (Employee, Student, Guardian, Applicant) will reference by ID. This sprint builds Person as its own aggregate, fully independent of `User` (Sprint 2.2 builds User on top of it).

## Person owns only identity, nothing else

Bilingual name parts (first/second/third/family × ar/en), DOB, gender, nationality, and a `photo` Media collection. Contact info, addresses, and identity documents are deliberately **separate child entities**, not columns on Person — a phone-number change needs its own audit trail distinct from an identity-field change (a phone change is a common OTP-hijack precursor), and a person can hold several of each.

Name parts are plain flat columns, never Spatie Translatable — names are transliterations, not translations, and need independent validation/search/export (agreed before implementation; see `App\Core\ValueObjects\PersonName`).

## `PersonName` and `IdentityDocumentReference` live in Core, not People

Both are explicitly named as Core value objects in the Blueprint (§1: "shared value objects (Money, DateRange, PersonName)"; §5: "Identity Document Reference ... a composite value"). This isn't a judgment call — Core may depend on nothing else in the system, so `App\Core\Services\DuplicateDetectionService` can only operate on these two VOs (plus `App\Core\ValueObjects\DuplicateSignals`/`DuplicateMatchResult`) without ever importing `App\Modules\People\Models\Person`.

## Duplicate detection is a domain-agnostic Core service

Per Addendum C2, the fuzzy-matching *algorithm* stays a generic Core service ("reusable for Vendor de-duplication in Inventory later") — People (or any future module) adapts its own rows into `DuplicateSignals` DTOs and calls `DuplicateDetectionService::score()`/`rank()`. Core never queries a database table itself; candidate lookup (`WHERE search_key = ?`) is the calling module's job.

### Scoring: identity-document evidence is *required* to reach "certain"

| Signal | Max points |
|---|---|
| First name similarity | 20 |
| Family name similarity | 20 |
| DOB exact match | 20 |
| Nationality exact match | 10 |
| Identity document exact match | 30 |

Name similarity is computed multibyte-safe: for Latin script, a consonant-skeleton comparison (strip vowels, collapse doubles) so "Mohammed"/"Muhammad"/"Mohamed"/"Muhammed" all collapse to `mhmd` — handling the exact transliteration-variance problem named in the sprint's Definition of Done — falling back to an edit-distance ratio for partial credit. For Arabic script, diacritics/tatweel are stripped and common letter variants (أ/إ/آ→ا, ى→ي, ة→ه) are normalized before comparison.

**Without any identity-document match, the maximum reachable score is 70** (20+20+20+10) — structurally below the 80-point `TIER_CERTAIN` threshold. This is deliberate, not tuned: name + DOB + nationality are exactly the signals two twins legitimately share, so the algorithm cannot classify a twin pair as a hard duplicate no matter how similar their names are. Only real identity-document evidence can push a match into "certain." Below 50 points, a candidate isn't returned by `rank()` at all.

## The dual-ID convention starts here: `HasPublicId`

Per Addendum D4, every domain aggregate (except Media, whose own PK is already a ULID) gets a `public_id` ULID column for external API/route representation — the internal auto-increment `id` must never leak externally. `App\Core\Concerns\HasPublicId` (a Core trait, matching `HasTemporalAssignment`'s precedent of being built ahead of a third consumer because it implements an already-frozen Blueprint decision) generates it on `creating()` and makes it the route key. Person is the first of many future consumers.

## `search_key`: computed and indexed from day one

The sprint's named risk was treating `search_key` as an afterthought needing a painful backfill later. It's computed automatically in `Person::booted()`'s `saving` hook via `DuplicateDetectionService::computeSearchKey()`, on every create *and* update (a name correction re-keys the row), and indexed (non-unique — it's a candidate-narrowing key, not a constraint).

## Identity documents: reissue is a new row, never an overwrite

`PersonIdentityDocument` uniqueness is scoped to the whole `(document_type, issuing_country, number)` triple at the database level — a passport renewal is a new row with a new number; the old row is kept and flagged `is_current = false`. This is distinct from a data-entry correction (a typo fix to an existing row), which Phase 3's Identity Correction tiering will govern with required-reason + approval gating for substantive fields — not built here.

## `ReassignsIdentityReferences`/`RedactsPersonalData`: minimal by design

Addendum C3's contracts are defined now (Core) so every future Person-referencing module implements them from its first migration, but deliberately with the simplest possible signature — `reassignPerson(oldId, newId)` / `anonymizePerson(personId)`, no `$dryRun` parameter yet. Addendum C7's dry-run/preview refinement is explicit Sprint 3.2 scope, once Merge itself is built and has a real shape to validate the richer signature against. Person's own implementation is "trivial" exactly as the Playbook names it: reassign/redact its own children, since Employee/Student/Guardian don't exist yet to hold their own references.

## Photo: private disk, no branch segment

Person is never branch-scoped (Addendum B6 — branch relevance always flows through a context aggregate, never a column on the identity anchor itself), so `Person` does not implement `App\Modules\Media\Contracts\HasBranchScopedMedia`. Its `photo` collection lives on the `private` disk (never CDN-fronted — a person's photo is not a public asset) with a `thumb` conversion.
