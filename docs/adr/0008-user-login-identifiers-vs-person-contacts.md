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

## Consequences

Every future module has one unambiguous rule: **read `Contact` to communicate with a person; read `users.email`/`phone` only to resolve a login attempt.** Notifications, Admissions, the Parent Portal, and CRM must not read `users.email`/`phone` as a messaging destination. A future "change my login email" feature must not cascade into `Contact`, and a future "update my contact info" feature must not cascade into `users`. Password-reset delivery (not yet built) follows the login-identifier side of this rule — it proves access to the account's registered recovery channel, which is `users.email`/`phone`, not `Contact` — a different concern from step-up authentication's OTP delivery, which correctly uses `Contact`.

## Alternatives Considered

- **Treat `users.email`/`phone` as mirrors of a Person's primary `Contact`, kept in sync automatically.** Rejected — collapses two different concerns (login credential vs. communication channel) into one, and any sync mechanism (which direction wins on conflict? does an edit to one require re-verifying the other?) reintroduces exactly the coupling `User ≠ Person` (§15) was designed to prevent.
- **Drop `users.email`/`phone` entirely and authenticate only via `username`, reading contact info from `Contact` for login resolution too.** Rejected — contradicts Blueprint §8's explicit field list for User, and would make login resolution depend on Person's child table, a boundary `User`'s single one-way FK to `Person` is meant to avoid crossing for routine authentication.
- **Enforce uniqueness/equality between a login email and some `Contact` row at write time.** Rejected — over-constrains a case the Blueprint never asked for, and produces confusing failure modes for the very first user who has a legitimate reason for them to differ.

## References

`docs/DOMAIN_BLUEPRINT.md` §8, §15. `docs/developer/identity-auth.md` (practical login/logout implementation this ADR governs). Raised during Sprint 2.2's approval, before Sprint 2.3.
