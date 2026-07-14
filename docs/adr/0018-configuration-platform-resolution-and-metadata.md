# ADR-0018: Configuration Platform — Resolution Algorithm & Metadata Model

**Status:** Accepted

**Date:** 2026-07-14

## Context

This component was referred to as the "Configuration Engine" through most of this review. On final review, that name undersells what it owns: a declarative registry, a resolution algorithm, a fourteen-field metadata model, dependency declarations, and integration points into two already-frozen Core engines (Approval, Audit). "Engine" implies one mechanism; this is a small platform in its own right, consumed by every other capability. This ADR names it **Configuration Platform** and specifies its complete contract.

## Decision

**1. Naming.** The component is the **Configuration Platform**, not "Configuration Engine" or "Settings." It is the sole realization of Policy & Configuration Governance (ADR-0017), owned entirely by Administration Platform per ADR-0016's boundary.

**2. The registration contract: `DeclaresSettingsSchema`.** Every module that wants a value resolved through the Configuration Platform declares it via a deploy-time manifest (code-reviewed, never runtime-mutable) stating: key, type, translatable-category (Blueprint B5's three-way test — identifier / system-vocabulary-Translatable / transliteration-flat-columns — never the cruder human-facing/technical split), default value, `required: bool`, eligible altitudes, versioned Y/N (mandatory `true` for any key feeding a calculation, per Blueprint §7), owning module, capability, Data Classification (per ADR-0011), approval-required, required-permission-to-view, required-permission-to-edit, restart-required, cache-TTL, dependency declarations (`requires: [{key, value}]`, simple equality only — no expression language; genuine conditional complexity is a signal the Workflow Engine is the right tool, not a smarter dependency graph), validation rules, migration strategy, and deprecation status.

Three fields proposed during review and explicitly **rejected**: an optional `audit-required` toggle (every Configuration write is audited by default, unconditionally — making it optional would create a class of silently-unaudited settings); an `encrypted` flag (a field needing encryption is not Configuration at all — it is a Provider Credential, ADR-0019, and the flag's presence signals miscategorization); an `environment` flag (a key's *existence* must never differ by environment — only its *value* differs, already covered by Deployment altitude; varying key existence by environment breaks "tested in staging = trusted in production").

**3. The resolution algorithm: pull-based, trace-returning.** `resolve(key, scopeContext)` returns `{value, resolvedAtAltitude, trace: AltitudeCheck[]}` — never a bare value. The trace is the ordered record of every altitude checked and whether each had a row, mirroring the Assignment pattern's `asOf(date)` idiom (Blueprint §6). Modules call this synchronously when they need a value; the Resolver never pushes values outward. No caching in the initial implementation — resolve live, promote to caching only once profiling proves it necessary, per Addendum A6's already-established discipline.

**4. Altitude chain: Platform → Deployment → Organization → School (once designed) → Branch**, plus **User Preferences as a separate, parallel, lower-ceremony mechanism** — never merged into the same audited chain (a personal theme choice carries none of the organizational-policy weight a Branch fee-rate override does). Override eligibility is declared per-key, default-deny, mirroring Blueprint B6's "burden of proof is on adding branch_id, not omitting it."

**5. Governance integration — no new mechanism.** A key with `approval-required: true` routes through the *existing* Approval Engine (Core, Sprint 1.2, frozen) as a new consumer, exactly as already proven twice (Identity Maintenance's Merge, Sprint 3.2; and now Configuration). A key with `safety-critical` classification (e.g., a Fire Alarm threshold, once Asset & Facility Stewardship exists) requires the same four-eyes discipline already frozen for Person Merge (Addendum C10) — no self-approval exception, not even for Super Admin.

**6. Configuration Objects are explicitly out of scope of this mechanism.** The moment a value needs multiple instances, sub-fields, or independent identity, it has left Configuration for Reference/Master Data — a real relational entity, owned by the relevant module (e.g., Organization's own Contacts/Addresses using the already-existing Address/Phone/Contact value objects, Blueprint §5), never a JSON blob inside the Configuration Platform's `value` column.

**7. Registry Pattern, formalized as a tenth named pattern.** Added to the shared-pattern catalog (Blueprint §6/§13) alongside Temporal, Assignment, Approval, Workflow, Media, Audit, Number Generator, Notification, Versioning, Duplicate-Detection: **a deploy-time, code-populated, low-cardinality catalog answering "what can exist," never "what happened."** The Configuration Registry is its first instance; the Provider Registry (ADR-0019) is its second. Any future component named "Registry" must pass this test — "Asset Registry" and "Template Registry" are explicitly rejected namings elsewhere in this review, since both are user-created, stateful Aggregates, not code-declared catalogs.

## Consequences

Every future module, regardless of which capability it serves, declares into this one mechanism and never invents its own settings storage. The metadata model's size (fifteen fields) is a deliberate, reviewed ceiling, not a starting point expected to grow further without cause — Phase 1's Developer Enablement deliverables (ADR-0022) exist specifically so declaring a new key is a fast, templated act, not a bespoke exercise each time.

## Alternatives Considered

- **An untyped key-value table**, values stored as strings. Rejected — the classic "Settings-as-God-Table" anti-pattern; loses type safety and hides parsing bugs until production.
- **Push-based resolution** (Administration Platform computes and notifies consumers of value changes). Rejected — couples Administration Platform to knowing when and how every consumer needs a value; pull-based, synchronous resolution is simpler, cacheable later without a redesign, and consistent with every other shared-mechanism pattern already in this codebase.
- **Encryption and audit-requirement as configurable per-key metadata.** Rejected, per Decision 2 above — both are better served as structural signals (miscategorization, and a hard constant) than as optional flags.

## References

`docs/adr/0016-administration-platform-data-boundary-and-philosophy.md`, `docs/adr/0017-administration-capability-model.md`. `docs/DOMAIN_BLUEPRINT.md` §5 (Value Objects), §6 (Assignment pattern, Temporal pattern), §7 (Historical Data Rules — versioned Configuration), Addendum A6 (caching discipline), Addendum B5 (translation convention), Addendum B6 (Branch Ownership override shape), Addendum C10 (four-eyes approval, the direct precedent for safety-critical Configuration).
