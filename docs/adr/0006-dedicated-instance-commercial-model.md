# ADR-0006: Dedicated Instance Per Customer (Not Shared Multi-Tenant)

**Status:** Accepted

**Date:** 2026-07-01

## Context

AlphaSchool is intended as a commercial product, potentially serving many customer schools over its lifetime. This created ambiguity: does "commercial" imply a shared multi-tenant SaaS architecture, or licensed dedicated deployments per customer? The two imply fundamentally different data models — shared multi-tenancy requires a `tenant_id` above every scoped entity and per-tenant Roles/Permissions; dedicated instances allow globally-scoped Users/Roles/Permissions/Settings within each customer's own database.

## Decision

AlphaSchool is deployed as a dedicated instance per customer school. "Global" throughout the architecture (Users, Roles, Permissions, Settings) means global *within one customer's deployment*, not shared across customers. No `tenant_id` exists anywhere in the schema.

## Consequences

Every authorization, branch-scoping, and settings decision made under this project is correct as designed, with no shared-tenant retrofit needed. A minimal `Organization`/`schools` licensing-metadata concept (Addendum A2/B) covers cross-instance vendor concerns (which modules a customer licensed) without implying shared tenancy. If this commercial model ever changes to shared multi-tenant SaaS, that is explicitly a full re-architecture trigger, not an incremental change — nearly every table's uniqueness scoping and the entire authorization model would need to be revisited.

## Alternatives Considered

- **Shared multi-tenant SaaS (one database serving many schools).** Rejected for the current product direction — would require `tenant_id` above School/Branch and per-tenant Roles/Permissions, a materially larger and different architecture than what's been designed.

## References

`docs/DOMAIN_BLUEPRINT.md` (commercial model note), Addendum A2, A9.
