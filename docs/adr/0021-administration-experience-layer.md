# ADR-0021: Administration Experience Layer

**Status:** Accepted

**Date:** 2026-07-14

## Context

ADR-0016 through ADR-0020 specify what Administration owns. None of it explains how an administrator operates a system with thousands of declared keys across five altitudes without drowning — a genuinely different problem: search, dependency navigation, effective-value resolution with provenance, health scoring, diffing, guided onboarding, readiness gating, packaging, import/export, rollback, and environment promotion. This ADR specifies that layer, governed by one constraint stated before anything else because it is the thing that keeps this from becoming the most dangerous capability of all eleven.

## Decision

**1. The governing constraint: this layer is read-mostly and derived. It owns almost nothing of its own.** Every capability below computes over data ADR-0016 through ADR-0020 already own — the Configuration Registry, the Provider Registry, the Audit Engine, the metadata model — never a parallel copy of that state. **Any caching in this layer must be invalidated by the exact same write-path every other consumer uses — no independently-populated cache, ever.** A cache that drifts from what the live Resolver would compute is a shadow God Module, worse than the God Module ADR-0016 already guards against, because it is the one administrators trust *more* than the real system.

The only things this layer is permitted to genuinely store are named, deliberate artifacts: Packages and Snapshots (Decision 8 below) — never shadow state.

**2. Global Search** is not a new mechanism — Blueprint Addendum D5's Scout-based Search abstraction, already frozen, applied to Administration's own registries. Every declared setting, Provider slot, and Workspace definition becomes a searchable document.

**3. Dependency Explorer and Impact Analysis are one service, two traversal directions.** A Dependency Graph, compiled at deploy time from every module's declared `requires` edges (ADR-0018) into a directed graph — derived, rebuilt from the manifests, never independently authored. Explorer walks forward ("what does this need"); Impact Analysis walks backward ("what needs this"), cross-referenced against active Packages and Health Rules.

**4. Effective Configuration Resolution is the Resolver's existing trace, exposed.** No new mechanism — ADR-0018's `resolve()` already returns `{value, resolvedAtAltitude, trace}`; this decision is the requirement that the trace is a first-class, presentable artifact, not merely an internal detail.

**5. Configuration Timeline is a filtered view over the existing Audit Engine** (Blueprint §13, Addendum A7), scoped to one key at one altitude. No new storage — every Configuration write is already mandatorily audited (ADR-0018).

**6. Configuration Health and Configuration Score.** A **Health Engine** runs declared `HealthCheckRule`s against the Registry's live state — orphaned keys, validation-rule violations (schema drift), deprecated-but-still-resolved keys — composing the Provider Registry's existing health-check callbacks (ADR-0019) as one input among several, not a parallel health mechanism. A composite **Score**, if built, **must be weighted by Data Classification and safety-critical metadata, and must never be presentable without the underlying Health Report one interaction behind it** — an unweighted, non-drill-through Score is explicitly rejected as a vanity metric that hides the failures that matter most (a missing safety-critical Fire Alarm threshold averaged into the same number as a missing SEO description).

**7. Readiness Checks are a discrete gate, distinct from Score.** A new extensible contract, `RegistersReadinessChecks`, joining the `DeclaresSettingsSchema`/`DeclaresProviderSlots` family (ADR-0018/0019) — any module, not only Administration Platform, registers domain-specific go-live gates (Admissions registering "at least one Registration Window exists" is a business-readiness rule, not a generic missing-configuration check). Readiness passes only when: zero missing required keys (ADR-0018's `required: true`), zero failing safety-critical health checks, and zero failing registered custom checks.

**8. Packages and Snapshots are one domain concept, distinguished only by provenance.** A named, versioned bundle of resolved Configuration-category values — never Reference/Master Data, never Content, both too instance-rich to template generically — using the Content Lifecycle Pattern (Draft → Published → Archived/Deprecated, a new named pattern added to Blueprint §6/§13's catalog alongside Registry, ADR-0018). A **Package** is hand-authored, for reuse across deployments (the generalization of White-Label deployment templates, Platform Extensibility, ADR-0017). A **Snapshot** is auto-captured, point-in-time, for one deployment's own restore/audit trail. Applying a Package must log every constituent key-write with the same audit granularity as an individual write — never one opaque "package applied" entry.

**9. Import / Export reuses the dry-run convention already proven in Sprint 3.2's `MergeOrchestrationService`.** Export filters the Resolver's output by scope and capability into a portable artifact. Import validates the entire artifact against current schema, dependency, and validation rules *before writing anything* — surfacing every conflict — and commits only on a clean pass or explicit per-conflict confirmation. Not a new pattern; the second real consumer of one this project already trusts.

**10. Rollback is never a literal rewind.** Consistent with Blueprint §7's oldest rule — never overwrite history — Rollback takes values from a point on the Audit Timeline and proposes them as a **new write**, passing through the identical validation, dependency, and approval pipeline as any other write. Reverting a safety-critical value to yesterday's setting still requires the same dual sign-off it would require going forward; Rollback is a source of proposed values, never a bypass of governance.

**11. Environment Promotion is Import/Export plus Diff, explicitly scoped to Organization/Branch-altitude Business Configuration only.** It must never move Deployment-altitude Technical Configuration (secrets, infrastructure parameters) — those remain outside the admin-editable surface entirely, per ADR-0016 §6's Tenancy Line, moved by CI/CD, never by an admin clicking "promote."

## Consequences

Of sixteen requested experience capabilities, exactly one new metadata field (`required: bool`, already captured in ADR-0018) and one new contract (`RegistersReadinessChecks`) were required. Everything else is composition over ADR-0016 through ADR-0020's mechanisms. This is treated as the strongest available evidence that the underlying capability model is sufficient: a genuinely enterprise-grade operability layer falls out of it almost entirely through recombination.

## Alternatives Considered

- **A materialized, independently-updated "current configuration state" cache powering this entire layer.** Rejected — this is precisely the shadow-God-Module risk named in Decision 1; any performance need this would address must be solved by caching *the Resolver's own computation*, invalidated on the Resolver's own write-path, never a separately-populated store.
- **A single Configuration Score with no classification weighting.** Rejected explicitly in Decision 6 — the risk of hiding safety-critical gaps behind an average outweighs the convenience of one number.
- **A general-purpose expression engine for the Dependency Graph** (arbitrary boolean logic across keys). Rejected — kept deliberately to simple equality preconditions; genuine conditional complexity is a signal for the Workflow Engine, not a reason to build a rules engine inside Configuration.

## References

`docs/adr/0016-administration-platform-data-boundary-and-philosophy.md` through `0020`. `docs/DOMAIN_BLUEPRINT.md` Addendum D5 (Search abstraction), §13 and Addendum A7 (Audit Engine, per-entity timeline), §7 (never overwrite history). `MergeOrchestrationService` (Sprint 3.2, `docs/developer/person-merge.md`) — the dry-run convention this ADR's Decision 9 reuses.
