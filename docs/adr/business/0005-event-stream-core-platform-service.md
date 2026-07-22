# BUS-0005: Event Stream Is a Core Platform Service (Shared Transport), Not a Learning-Specific "Learning Signal"

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Learning, and, in principle, every domain that already has its own "Domain Events" section in `docs/BUSINESS_BLUEPRINT.md` (School Operations, Smart Campus, Inventory, and others as they're written)

**Related ADRs:** none

## Context

"Learning Signal" was proposed as a granular, high-volume, continuous telemetry stream (every interaction, not just formal assessment) feeding the Continuous Mastery model.

## Problem

Should this telemetry stream be modeled as a Learning-domain-owned concept, or as shared infrastructure?

## Alternatives Considered

- **Learning Signal, owned by Learning** — the original name and framing. Rejected: Library, Smart Campus, and Attendance all produce events an AI capability would plausibly want to consume; naming and scoping this to Learning specifically would force every other domain to reinvent the same transport independently.
- **One shared event schema every domain must conform to** — rejected as a new, worse coupling point: it would require every domain to agree on one common event vocabulary, undermining the domain-ownership discipline already established throughout this document.
- **Event Stream as shared transport infrastructure (a Core Platform Service), each domain still owning its own event definitions** — accepted.

## Final Decision

Renamed from "Learning Signal" to **Event Stream**: a generic pub/sub transport layer, a Core Platform Service alongside Approval Engine and Emergency Coordination. Each domain continues to own the *definition and content* of its own events (Library's `BookBorrowed`, Learning's `LessonCompleted`) exactly as already practiced in every domain's own Domain Events section — Event Stream is the pipe, not a shared schema.

## Why This Decision Was Chosen

This is the logical conclusion of a pattern already implicit everywhere in this document — every domain already lists its own Domain Events, siloed. Naming the missing unifying transport, while explicitly *not* centralizing event definitions, avoids trading one coupling problem (Learning-only telemetry) for a worse one (a single shared event vocabulary owned by nobody).

## Consequences

Easier: any future AI capability (or any domain, not only AI) can consume events across domains without each domain independently building its own pub/sub mechanism. Harder: Event Stream itself needs to be formally specified as a Core Platform Service — currently only named, not designed — see Open Architecture Questions.

## Future Implications

Once formally specified, every domain's existing "Domain Events" section becomes, retroactively, a declaration of what that domain publishes onto Event Stream, without needing to change the events themselves.

## Traceability

- **Business requirement:** AI capabilities (Mastery, Intervention) need cross-domain behavioral signal, not just Learning-internal data.
- **Introduced in:** the "Forget Moodle, Forget Canvas" AI-first entities discussion; renamed and rescoped in the immediate follow-up turn.
- **Depended on by:** BUS-0002 (Mastery needs a signal source), BUS-0003 (AIDecision records may be triggered by Event Stream events).
