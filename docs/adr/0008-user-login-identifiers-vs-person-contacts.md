# ADR-0008: User Login Identifiers Are Independent of Person Contacts

**Status:** Accepted

**Date:** 2026-07-05

## Context

Sprint 2.2 gave `User` its own `email` and `phone` columns, per `docs/DOMAIN_BLUEPRINT.md` §8's literal field list (`username/email/phone/password/status/last_login_at`). Sprint 2.1 separately gave `Person` a `contacts` child table (typed, multi-row, independently verifiable) for real-world communication channels.

Both `users.email`/`users.phone` and a Person's `Contact` rows can hold what looks like the same kind of data — an email address, a phone number — which raises a real question the Blueprint doesn't resolve explicitly: are these the same fact recorded twice, or two genuinely different concerns that happen to share a data shape? Left undecided, every future module that touches either (Notifications, Admissions, the Parent Portal, CRM) would have to guess, and different modules guessing differently is exactly how identity data quietly drifts out of sync. This ADR resolves the ambiguity before a second consumer has to guess.

## Decision

`users.email`/`users.phone` and Person's `Contact` rows are **two independent concerns that intentionally may hold different values**, never a single fact mirrored in two places.

1. **They can intentionally differ.** `users.email`/`phone` are *login identifiers* — credentials that identify which account to authenticate against, interchangeable with `username` in the login flow. A Person's `Contact` rows are *communication channels* — plural, typed (`phone`/`email`), independently verifiable via `verified_at`, with an `is_primary` flag. A user may log in with a work email while their verified personal contact email is different, and that is correct, not drift.

2. **Person's `Contact` is the canonical communication channel.** Any module that needs to actually *reach* a person — Notifications sending a message, Admissions delivering a decision, step-up authentication delivering an OTP (already built this way in `StepUpAuthenticationService`) — reads from `Contact`, filtered to `verified_at IS NOT NULL`, never from `users.email`/`phone`. `users.email`/`phone` exist solely to resolve *which account* a login attempt is for.

3. **No automatic sync in either direction.** Changing a login email/phone never touches Person's `Contact` rows, and changing or adding a `Contact` never touches `users.email`/`phone`. Both are explicit, independently-triggered actions:
   - Changing a login identifier is an **account-security operation** (it changes how someone can authenticate) and must go through its own deliberate, verified flow when built — never a side effect of a Person/Contact edit elsewhere.
   - Adding or editing a `Contact` is an **identity/communication-preference operation** and must never silently alter how someone signs in.

   Auto-syncing would violate this separation in both directions: it could overwrite a verified `Contact` with an unverified login credential, or silently change a login identifier because someone updated an unrelated contact preference.

4. **Exactly one of each today; a future multiple-login-identifiers need is a schema change, not a policy change.** `users` currently has flat, singular `username`/`email`/`phone` columns — one login identity per account, not a collection. If a real need for multiple verified login identifiers per account arises later (e.g. signing in with any of several linked emails), that is a new child table analogous to `Contact` (a `user_login_identifiers` table), decided when a real consumer needs it — not spec'd speculatively now, per the "promotion, not prediction" principle already applied elsewhere (Addendum B1, D1).

5. **Preferred Communication Contact.** A Person may hold several verified contacts of the same type (two verified emails, two verified phones). For **every contact type**, exactly one may be designated preferred/default for that type — a preferred email and a preferred phone are independent designations, not one single preferred contact across all types. This is `Contact.is_primary` — the flag already built in Sprint 2.1 — not a new field; this ADR fixes its meaning as "preferred within this contact's type," since the column itself doesn't yet encode or enforce that scoping.

   - **Every communication module reads the preferred verified contact of the type it needs by default** — Notifications, CRM, Admissions, the Parent Portal, and any future communication module. Which *type* to use for a given situation (email vs. SMS vs. WhatsApp) is that module's own business decision; which specific *contact* to use once a type is chosen is always "the preferred one," never "whichever row happens to be first."
   - **Fallback when no contact is marked preferred, in order:**
     1. Exactly one verified contact of the needed type exists → use it (there is no real ambiguity to resolve).
     2. More than one verified contact of the needed type exists and none is marked preferred → do not guess. This is a data-quality gap (a preferred one should have been designated), not a silent pick — the calling module must prompt for a designation or skip that channel for this delivery, never choose arbitrarily among ambiguous candidates. Silently guessing among several real contacts is a materially different risk than the "first one, only one exists" case above (e.g. an OTP or medical notification reaching the wrong device).
     3. No verified contact of the needed type exists at all → that channel cannot be used for delivery; the calling module falls through to another verified type if its workflow supports one, or fails explicitly.
   - **This does not touch authentication.** `users.email`/`users.phone` have no concept of "preferred" and never will under this ADR — a User has at most one login email and one login phone already (point 4). Preferred/default is exclusively a `Contact` (communication) concept.
   - **Enforcement is not built yet.** Nothing today prevents two `Contact` rows of the same type/person both being marked `is_primary`, since the flag predates this ADR's scoping rule. This is a documented policy now, an enforced one (a partial unique index or application-level guard, scoped per person+type) whenever the first real consumer — most likely Notifications — is built and needs it, per this project's standing "define the contract now, enforce it when a real consumer exists" pattern (the same treatment already given to Identity Maintenance's contracts and Person's `reassignPerson`/`anonymizePerson`).

## Consequences

Every future module has one unambiguous rule: **read `Contact` to communicate with a person; read `users.email`/`phone` only to resolve a login attempt.** Notifications, Admissions, the Parent Portal, and CRM must not read `users.email`/`phone` as a messaging destination, and must select the *preferred* verified `Contact` of the needed type by default, never an arbitrary one, falling back per the ordered rule above when no preference is designated. A future "change my login email" feature must not cascade into `Contact`, and a future "update my contact info" feature must not cascade into `users`. Password-reset delivery (not yet built) follows the login-identifier side of this rule — it proves access to the account's registered recovery channel, which is `users.email`/`phone`, not `Contact` — a different concern from step-up authentication's OTP delivery, which correctly uses `Contact`. Whichever module first builds real delivery (most likely Notifications) must also add the per-person-per-type enforcement on `Contact.is_primary` named above — it is scoped policy now, not yet a database guarantee.

## Alternatives Considered

- **Treat `users.email`/`phone` as mirrors of a Person's primary `Contact`, kept in sync automatically.** Rejected — collapses two different concerns (login credential vs. communication channel) into one, and any sync mechanism (which direction wins on conflict? does an edit to one require re-verifying the other?) reintroduces exactly the coupling `User ≠ Person` (§15) was designed to prevent.
- **Drop `users.email`/`phone` entirely and authenticate only via `username`, reading contact info from `Contact` for login resolution too.** Rejected — contradicts Blueprint §8's explicit field list for User, and would make login resolution depend on Person's child table, a boundary `User`'s single one-way FK to `Person` is meant to avoid crossing for routine authentication.
- **Enforce uniqueness/equality between a login email and some `Contact` row at write time.** Rejected — over-constrains a case the Blueprint never asked for, and produces confusing failure modes for the very first user who has a legitimate reason for them to differ.
- **One single preferred contact per Person, across all types.** Rejected — a person's preferred email and preferred phone are independent facts (someone may prefer a work email but their personal phone for SMS); collapsing them into one flag would force a false choice between types and lose information a single-preferred-per-type design keeps.
- **Silently fall back to "the most recently added/verified contact" whenever none is marked preferred, regardless of how many exist.** Rejected — indistinguishable from guessing once more than one candidate exists, for exactly the failure mode (a notification reaching the wrong device) this policy exists to prevent. Falling back automatically is only safe when there is genuinely no choice to make (exactly one candidate).

## References

`docs/DOMAIN_BLUEPRINT.md` §8, §15. `docs/developer/identity-auth.md` (practical login/logout implementation this ADR governs). `App\Modules\People\Models\Contact` (Sprint 2.1) — the `is_primary` column this ADR's Preferred Communication Contact rule scopes. Raised during Sprint 2.2's approval, before Sprint 2.3.
