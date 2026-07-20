# ADR-0023: Zod-First API Contracts via a Shared Contracts Package

**Status:** Accepted

**Date:** 2026-07-19

## Context

The admin frontend (`admin/`) already depends on `zod`, `react-hook-form`, and `@hookform/resolvers`, but no shared-contracts convention exists yet: nothing stops a future module from hand-writing a `SettingsResponse` interface in the frontend that silently drifts from what `backend/` (Laravel) actually returns, or from re-validating the same shape three different ways (once for the API call, once for the form, once for a hand-written TypeScript type). `admin/` is currently a standalone npm package with no workspace tooling, and no `packages/` directory exists — this decision is made ahead of the first real cross-cutting schema (e.g. Settings) rather than retrofitted once duplication already exists, consistent with the project's established "design frozen before code" discipline (see ADR-0022 Decision 3, and the Admin Design System's small-slice precedent).

This ADR freezes the rule, not the scaffolding. `packages/contracts` itself is built when the first real feature schema needs it.

## Decision

**1. Zod schemas are the single source of truth for every API contract.** For a given contract, runtime validation, the TypeScript type, form validation, request validation, and response validation must all derive from one `z.object(...)` definition — never hand-written in parallel. Types are always obtained via `z.infer<typeof XSchema>`, never declared as a separate `interface`.

**2. A dedicated workspace package, `@alphaschool/contracts` (`packages/contracts/`), owns every schema.** Structure is feature-first, one subfolder per module (`common/`, `auth/`, `users/`, `organizations/`, `branches/`, `academic-years/`, `settings/`, `students/`, `parents/`, `teachers/`, `finance/`, `hr/`, `library/`, `transport/`, `api/`, ...), mirroring the module boundaries already established in `docs/DOMAIN_BLUEPRINT.md`. Each feature folder exposes `*.request.ts`, `*.response.ts`, `*.errors.ts`, `*.schemas.ts`, and an `index.ts` barrel. A folder is created for a module only when that module's first real endpoint is built — the list above is the eventual shape, not scaffolding to create today.

**3. Every endpoint ships three schemas: Request, Response, Error.** No endpoint is considered complete without all three. The frontend never consumes unvalidated JSON — every API response is passed through `ResponseSchema.parse()` or `.safeParse()` before use, and the API client (`api.get(...)` etc.) returns validated, typed data, not raw `response.data`.

**4. Errors are typed contracts, not ad hoc shapes**: `ApiErrorSchema`, `ValidationErrorSchema`, `AuthorizationErrorSchema`, `ConflictErrorSchema`, `NotFoundErrorSchema`, `RateLimitErrorSchema`, `ServerErrorSchema`. Every endpoint's error path returns one of these.

**5. Common primitives are defined once in `common/`** — `EmailSchema`, `PhoneSchema`, `UUIDSchema`, `SlugSchema`, `UrlSchema`, `DateSchema`, `DateTimeSchema`, `LocaleSchema`, `LanguageCodeSchema`, `CurrencySchema`, `PercentageSchema`, `PositiveIntegerSchema`, `PasswordSchema`, `FileIdSchema`, `MediaIdSchema`, `BranchIdSchema`, `AcademicYearIdSchema`, `OrganizationIdSchema`, `UserIdSchema` — and reused, never redefined, across feature folders.

**6. Naming is fixed**: `<Entity>Schema`, `Create<Entity>RequestSchema`, `Create<Entity>ResponseSchema`, `Update<Entity>RequestSchema`, `List<Entity>ResponseSchema`, `<Entity>ErrorSchema`.

**7. Feature code imports only from the package root**, never through deep relative paths and never reaching into another feature's internal files:

```ts
// Good
import { UserSchema } from "@alphaschool/contracts";

// Bad
import { UserSchema } from "../../../../../schemas/user";
```

**8. Forms reuse the exact same schema the API uses** — `react-hook-form` + `zodResolver()` bound to the shared contract, not a parallel form-only schema.

**9. Contracts are versioned. A breaking change to an existing schema requires all four of:** a contract version increment, a `CHANGELOG` entry in `packages/contracts/`, written migration notes (what changed, what a consumer must update), and an explicit compatibility review of every existing frontend/backend consumer of that schema before the change merges. None of the four is optional or satisfied implicitly by the others — a version bump without migration notes, or a changelog entry without a consumer review, does not meet this rule. This is the same discipline this project already applies to identity/contract boundaries (ADR-0009), made concrete for `packages/contracts`.

**10. Documentation and SDK generation are declared future consumers**, not built now. The schemas are written so that OpenAPI/doc generation and client SDK generation can be layered on later without reshaping the contracts — but no generator is wired up as part of this ADR.

**11. `packages/contracts` is the single public contract consumed by frontend applications — manual duplication of an API contract is prohibited.** No frontend module may hand-maintain a parallel description of a shape that also has a Zod schema (a duplicate PHP-mirroring interface, a second validation rule set, a copy-pasted enum list). Where practical, contracts are generated or synchronized from a single authoritative source rather than hand-kept-in-sync by convention alone — e.g. deriving the Zod schema from the Laravel FormRequest/Resource definition (or vice versa) via a generation step, rather than a human updating both by hand on every change. "Whenever practical" is deliberately not "always required at launch": until a generation/sync mechanism exists, the fallback is that the Zod schema in `packages/contracts` is authoritative and the Laravel side is written to match it, never the reverse, and never a second frontend-side definition alongside it.

## Consequences

Every future module (`settings`, `students`, `finance`, `hr`, ...) starts its first endpoint by writing its Zod schemas in `packages/contracts/src/<module>/` before any backend controller or frontend hook is written — this is now the ADR-level gate, with the same review weight as any other item in this ADR, not a style preference a future contributor or AI agent can skip under deadline pressure. `admin/` gains a real dependency on a workspace package it does not yet have; the first module to need a contract triggers the actual `packages/` + workspace-tooling setup (workspace manager, build step for `packages/contracts`, wiring `admin/`'s `package.json`) as part of that module's own small slice, not as a separate speculative infrastructure task.

Laravel (`backend/`) request/response shapes must match the corresponding Zod schema, and Decision 11 means that match is no longer left to unenforced hand-keeping wherever a generation/sync mechanism is practical to build — e.g. generating the Zod schema from the Laravel FormRequest/API Resource, or validating one against the other in CI. Until such a mechanism exists for a given contract, `packages/contracts` is unambiguously authoritative and the PHP side is written to match it by hand — this is a stated interim gap, not a resolved one, and closing it (via generation or a CI drift check) is expected future work rather than something this ADR claims is already solved.

Decision 9 turns every breaking contract change into a four-part checklist (version bump, changelog, migration notes, consumer review) rather than a single-line schema edit — this adds real friction to changing a shipped contract, which is the intended effect: it makes breaking a consumer a deliberate, reviewed act rather than an accidental side effect of "just updating the type."

## Alternatives Considered

- **Per-module ad hoc validation (each feature writes its own zod schemas inline, no shared package).** Rejected — this is close to the status quo and is exactly what produces silent frontend/backend drift; the entire point of this ADR is a single source of truth shared across every consumer (API call, form, type).
- **Hand-written TypeScript interfaces with separate runtime validation (e.g. `interface` + a hand-rolled validator or class-validator equivalent).** Rejected — guarantees the two definitions diverge over time; Zod's `z.infer` removes the duplication entirely.
- **Generating TypeScript types from the Laravel/PHP side (e.g. OpenAPI-first, PHP as source of truth).** Not rejected outright — Decision 11 explicitly leaves this open as a "generate/sync from a single authoritative source" path. What's rejected is treating it as required before any contract can ship: until a generation mechanism is actually built, `packages/contracts` (Zod, TypeScript-first) is authoritative and PHP is written to match by hand, because the admin frontend is TypeScript-first today and blocking on generator tooling would stall every module.
- **Leaving backend/frontend sync as an unenforced convention indefinitely (no drift-prevention rule at all).** Rejected — this is exactly the status quo Decision 11 exists to close off; "manual duplication is prohibited" is a stronger claim than "please try to keep these in sync."
- **Allowing breaking contract changes as a normal in-place edit, relying on code review to catch consumer breakage.** Rejected — contracts are public interfaces to every current and future consumer (frontend, and eventually SDK/doc generation per Decision 10); an unversioned breaking change is discoverable only at runtime for whichever consumer wasn't in the reviewer's head at the time.
- **Scaffolding `packages/contracts` and the workspace tooling immediately, before any real schema exists.** Rejected for this ADR — no feature currently needs it, and building empty infrastructure ahead of a concrete consumer contradicts the project's established small-slice, design-before-code discipline (ADR-0022 Decision 3, 7). Confirmed again when adding Decisions 9/11: the first real scaffolding trigger remains the Configuration Platform / Settings module.

## References

`docs/DOMAIN_BLUEPRINT.md` (module boundaries this package's folder structure mirrors). `docs/adr/0022-administration-platform-delivery-principles.md` (small-slice / design-before-code precedent this ADR follows). `docs/adr/0009-identity-maintenance-contract-boundary.md` (prior use of "contract" as a versioned, governed boundary in this project). `admin/package.json` (existing `zod`, `react-hook-form`, `@hookform/resolvers` dependencies this ADR builds on).
