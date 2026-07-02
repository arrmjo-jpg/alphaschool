# The Temporal Pattern — `HasTemporalAssignment`

Every effective-dated fact in AlphaSchool (a teacher's homeroom assignment, an employee's branch membership, a guardian's relationship to a student) follows the same shared pattern instead of each module inventing its own `effective_from`/`effective_until` handling. See `docs/DOMAIN_BLUEPRINT.md` §6 ("Effective Dating Pattern") and Addendum A3/B1 for why this exists and why it's a Core, not a per-module, concern.

## The interval convention: half-open, `[from, until)`

`effective_until` is **exclusive**. A period ending on `2026-06-01` and the next one starting on `2026-06-01` are adjacent, not overlapping. This is deliberate:

- Chaining consecutive periods never needs `+1`/`-1` day arithmetic.
- There's no ambiguity about which period "owns" a shared boundary day (the new one does).
- `App\Core\ValueObjects\DateRange::overlaps()` encodes this precisely — see its tests (`tests/Unit/Core/ValueObjects/DateRangeTest.php`) for the exact edge cases this resolves.

A null `effective_until` means "still ongoing" (open-ended).

## Required columns

Any table adopting `HasTemporalAssignment` needs:

| Column | Type | Notes |
|---|---|---|
| `effective_from` | `date` | Required |
| `effective_until` | `date`, nullable | Exclusive; null = ongoing |
| `status` | `string` | `scheduled` \| `active` \| `ended` \| `cancelled` — see below |
| `reason_code_id` | `unsignedBigInteger`, nullable, FK to `reason_codes` | Set when closing or cancelling |
| `ended_by_id` | `unsignedBigInteger`, nullable | Who closed/cancelled it |

## `status` is administrative, not authoritative

Whether a record is *actually* in effect right now is always derived from the date range (`asOf()`/`active()` query scopes), never trusted from the stored `status` value alone. This means a `scheduled` record whose `effective_from` has already arrived is correctly picked up by `active()` without anything needing to flip its status via a cron job — see `HasTemporalAssignmentTest`'s "status label is administrative only" test for the exact scenario this protects against.

`cancelled` is the one status that's authoritative: a cancelled record never competes for exclusivity and is excluded from every query scope, regardless of its dates. Use `cancelAssignment()` for a record that should never have existed (a mistake); use `closeAssignment()` for a record that legitimately concluded.

## Adopting the trait

A consuming model must implement two methods:

```php
use App\Core\Concerns\HasTemporalAssignment;

class HomeroomTeacherAssignment extends Model
{
    use HasTemporalAssignment;

    // Which other rows compete for exclusivity. Two teachers cannot both
    // be the active homeroom teacher of the same section at the same time.
    public function temporalScopeAttributes(): array
    {
        return ['section_id' => $this->section_id];
    }

    // Which reason_codes.context this model's reasons are registered
    // under -- e.g. HR seeds 'homeroom_teacher_assignment' reasons
    // ('teacher_left', 'reassigned') when this module is actually built.
    public function temporalReasonContext(): string
    {
        return 'homeroom_teacher_assignment';
    }
}
```

That's it — creating, closing, or cancelling a record automatically gets overlap validation and reason-code enforcement for free.

## Reason codes: a pure value object, a DB-backed lookup, and a validation rule — three separate things

- **`App\Core\ValueObjects\ReasonCode`** — a pure value object. Validates only that a string is non-empty, lowercase snake_case. No database access, ever. Fast and trivial to construct in tests.
- **`App\Core\Models\ReasonCode`** — the Eloquent model backing the `reason_codes` table, the actual catalog of which codes are valid per `context`. Core owns the table shape; **each module seeds its own context's reasons** as that module is actually built (HR seeds `employment` reasons in Phase 6, Academic seeds `enrollment` reasons in Phase 4/5) — see `database/seeders/ReasonCodeSeeder.php`, which is deliberately empty as of Sprint 1.1.
- **`App\Core\Rules\ValidReasonCode`** — a Laravel `ValidationRule` for FormRequests, checking a submitted code against the DB-backed catalog for a given context. Use this at the HTTP boundary, where a user actually submits a reason — not inside the value object itself.

Don't conflate these. The value object being DB-free is deliberate — it's what `closeAssignment()`/`cancelAssignment()` accept as a type-safe parameter without forcing every call site to hit the database just to construct one.

## Why the overlap guard is a `saving` hook, not something you call manually

`HasTemporalAssignment::bootHasTemporalAssignment()` hooks into every `save()` automatically. You cannot accidentally create or update a record into an overlapping state — the guard runs whether you're calling `Model::create()` directly, through a service, or from a seeder. It only re-runs the (small) competing-scope query when a temporally-relevant column actually changed (`effective_from`, `effective_until`, `status`, or any of `temporalScopeAttributes()`'s keys) — an unrelated column update on an existing record doesn't re-trigger it.

## `temporalScopeAttributes()` pitfall: scope by the right combination, not just the "obvious" one

The trait doesn't know or care what "the same scope" means — that's entirely up to your implementation. Get this wrong and the overlap guard silently enforces the wrong exclusivity rule. The concrete, realistic mistake: if `employee_branches` is ever built with

```php
public function temporalScopeAttributes(): array
{
    return ['employee_id' => $this->employee_id]; // WRONG
}
```

this incorrectly prevents an employee from being active at a second branch while already active at a first — but `docs/DOMAIN_BLUEPRINT.md` explicitly allows an employee to belong to multiple branches simultaneously (a genuine many-to-many). The correct scope is the **pair**:

```php
public function temporalScopeAttributes(): array
{
    return ['employee_id' => $this->employee_id, 'branch_id' => $this->branch_id]; // correct
}
```

— exclusivity on "this employee at this specific branch," not "this employee at all." Always ask: *what combination of columns, together, must never have two overlapping active rows?* — not just "which single column feels like the owner."

## Two deliberate scope boundaries — not gaps

- **Role assignments.** Spatie's `model_has_roles` is a third-party table this trait cannot be applied to directly. A future time-bound role delegation (e.g. a substitute teacher granted a role for two weeks) needs a separate tracking table (e.g. `role_assignments`) that adopts `HasTemporalAssignment` itself and drives Spatie's real `assignRole()`/`removeRole()` via event listeners at the boundary dates — not a retrofit onto Spatie's own pivot.
- **Recurring schedules.** A timetable slot has two independent concerns: which term it's valid for (this trait's job) and its recurring day-of-week/time-of-day pattern (not this trait's job, and it should never try). Baking recurrence handling into Core would be exactly the "Core needs domain richness" mistake `docs/DOMAIN_BLUEPRINT.md` Addendum B1 exists to prevent.

## What this trait deliberately does not do

- It does not chain records together (e.g. maintaining a `previous_enrollment_id`/`next_enrollment_id` link). That's domain-specific to each consumer — Enrollment's chain means something different from Employment's, and forcing one generic chaining mechanism here would be exactly the "Core needs domain richness" mistake `docs/DOMAIN_BLUEPRINT.md` Addendum B1 warns against.
- It does not open the *next* period when you close one. `closeAssignment()` only closes the current record — deciding what the next period should look like (same grade? different branch? nothing at all?) is business logic that belongs in the consuming module's own action/service class.
