# Core

**Layer:** Shared kernel (depended on by everyone; depends on nothing else in this application)

Shared kernel: the temporal/Assignment pattern, Number Generator, Approval Engine, Audit Engine, Duplicate-Detection service, shared value objects (Money, DateRange, PersonName), event-dispatch infrastructure and observability.

**Rule:** Core must be domain-agnostic. Nothing here may reference a `App\Modules\*` namespace. See `docs/DOMAIN_BLUEPRINT.md` §1 and Addendum B1 for the inclusion/exclusion tests.

Populated starting Sprint 1.1 — empty as of Sprint 0.1 (placeholder only, no code yet).
