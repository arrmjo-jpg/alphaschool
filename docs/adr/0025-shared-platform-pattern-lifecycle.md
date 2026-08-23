# ADR-0025: Shared Platform Pattern Lifecycle

**Status:** Accepted — a process rule, not a technical claim requiring empirical validation against a domain consumer, so it does not itself carry a Proposed/Validation-Plan cycle the way `ADR-0024` does. Matches the precedent already set by `docs/adr/business/0016-domain-folder-split-threshold.md` and `docs/adr/business/0021-adr-granularity-one-decision-per-adr.md` — both self-referential governance rules, written directly as Accepted.

**Date:** 2026-07-26

## Context

`ADR-0024`'s development — discovering the Actor↔Governed-Resource shape while working on Learning's Participation Model, generalizing it, deliberately stress-testing it against real counterexamples across the platform, documenting it as Proposed with precise Terminology and a checkable Validation Plan — followed a clear, repeatable sequence that was never separately named until now. This ADR formalizes that sequence as the mandatory lifecycle for any future shared platform pattern proposal.

## Decision

Every shared platform pattern must pass through seven stages before being treated as a stable platform guarantee:

1. **Discovered inside a domain** — never designed top-down, speculatively, ahead of a real domain need. The same anti-premature-generalization discipline already governing `docs/adr/business/0016-domain-folder-split-threshold.md`, now stated as binding for the technical pattern catalog too.
2. **Abstracted into a platform candidate** — generalized beyond the originating domain's own vocabulary, deliberately, not left as a domain-specific mechanism other domains happen to resemble.
3. **Challenged using counterexamples** — actively searched for cases that break the abstraction, not merely cases that confirm it. A candidate checked only against supporting examples has not been challenged, regardless of how many examples were gathered.
4. **Documented as Proposed** — a full ADR: Terminology precise enough to prevent silent scope creep (`ADR-0024`'s Actor definition, explicitly excluding Service Account/Device/Agent without their own ADR, is the model), and explicit Decision Criteria a future author can apply without re-deriving the reasoning from scratch.
5. **Consumed by at least one real domain** — built against, not merely reasoned about.
6. **Promoted to Accepted only after that consumption succeeds against a stated Validation Plan** — never promoted on the strength of argument alone, no matter how thorough the argument was.
7. **May be deprecated if future evidence invalidates it** — Accepted is not permanent immunity from revision. `docs/adr/template.md`'s own Status vocabulary already includes "Superseded by ADR-YYYY | Deprecated"; a pattern is not exempt from that path just because it is foundational or widely consumed by the time the invalidating evidence arrives.

## Why This Decision Was Chosen

Not asserted in the abstract — extracted from what already happened. `ADR-0024` is the worked example: stages 1–4 are complete and visible in that document (Context traces the discovery, the Decision section shows the abstraction and the stress test, the ADR itself is the Stage-4 artifact); stages 5–6 are explicitly pending, stated as its own Validation Plan rather than assumed; stage 7 was not yet named anywhere before this ADR, which is the one real gap this document closes.

## Consequences

Future pattern proposals — the Workflow Engine is the next likely candidate once it's actually built — must be checked against this same seven-stage sequence rather than fast-tracked to Accepted on the strength of a single compelling argument, the identical discipline `ADR-0024` was just held to. A pattern that skips stage 3 (no genuine counterexample search) or is promoted past stage 6 without a real consumer should be treated as under-substantiated regardless of how coherent its design reads.

**Honest gap, not claimed as resolved:** whether the Registry Pattern (`ADR-0018` Decision 7) itself followed stage 3 — a deliberate, adversarial counterexample search, as opposed to being recognized directly from two already-built instances (Configuration Registry, Provider Registry) — has not been verified. This ADR does not retroactively claim Registry Pattern's history complied; it only binds future pattern proposals going forward.

## Alternatives Considered

- **Treat this lifecycle as an informal norm, never written down.** Rejected — per the project's own documentation-first governance rule: a process that shaped `ADR-0024` this materially, existing only in conversation, is precisely the kind of undocumented architectural knowledge that rule exists to prevent.
- **Require each of the seven stages to be its own separate ADR.** Rejected as unnecessary ceremony — `ADR-0024` itself already demonstrates stages 1–4 living in one document, with stages 5–7 as forward-pointing commitments (its Validation Plan) rather than separate artifacts each needing their own approval cycle.

## References

`docs/adr/0024-shared-role-pattern-actor-governed-resource.md` (the worked example this lifecycle is extracted from — Terminology, Decision Criteria, and Validation Plan sections are the concrete instances of stages 3–6). `docs/adr/template.md` (the Status vocabulary stage 7 depends on). `docs/adr/0018-configuration-platform-resolution-and-metadata.md` Decision 7 (Registry Pattern — the earlier pattern this ADR's Consequences section explicitly declines to claim retroactive compliance for). `docs/adr/business/0016-domain-folder-split-threshold.md`, `docs/adr/business/0021-adr-granularity-one-decision-per-adr.md` (the direct precedent for a self-referential governance ADR being written as Accepted rather than Proposed).
