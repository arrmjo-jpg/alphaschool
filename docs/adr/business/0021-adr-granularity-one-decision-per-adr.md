# BUS-0021: ADR Granularity — One Central Decision per ADR, Split Only Once Sub-Decisions Stop Being Coupled

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** all — this governs the documentation structure itself, not any one domain's content, the same scope as BUS-0016

**Related ADRs:** BUS-0016 (same category of documentation-process rule, same shape of threshold-based solution), BUS-0017 (the ADR that prompted this rule — not retroactively split by this decision)

## Context

BUS-0017 grouped five related decisions (Faculty, Academic Department, Stage, Curriculum Path, Curriculum Specification) into one ADR, following the earlier resolution to consolidate by theme rather than fragment the Decision Log into dozens of tiny entries. Reviewing the result surfaced a real risk: if this consolidation pattern continues unchecked, a themed ADR can keep absorbing new decisions indefinitely and grow into a full design document wearing an ADR's name, rather than recording one traceable decision.

## Problem

How many independent decisions may one ADR hold before it should split instead, and does BUS-0017 need splitting now?

## Alternatives Considered

- **One ADR per decision, always** — rejected again, for the same reason the flat-numbering-vs-domain-prefix question was resolved: Faculty/Academic Department/Stage/Curriculum Path/Curriculum Specification were reasoned about together in service of one question ("what is Academic's organizational hierarchy"), and splitting them apart would scatter one coherent argument across five files, each restating the same Context and Alternatives.
- **No limit at all, consolidate freely by theme** — rejected. Unchecked, a themed ADR can grow into a full design document that no longer functions as a single reviewable decision record, defeating the purpose of an ADR.
- **Split BUS-0017 right now, before it's referenced further** — rejected. Its five sub-decisions were genuinely resolved together as one argument; splitting them today would scatter an already-coherent record for no new information, purely to satisfy a rule that didn't exist when it was written.
- **A coupling-based threshold, applied going forward only** — accepted.

## Final Decision

An ADR should record one central architectural decision. A themed ADR may hold several sub-decisions when they were reasoned about and resolved together as one argument (as BUS-0017 did). Once a themed ADR's sub-decisions stop being tightly coupled — i.e., a new sub-decision could be understood, challenged, or reversed independently of the others already recorded — it becomes its own ADR instead of being appended to the existing one. This is forward-looking only: BUS-0017 is not retroactively split.

## Why This Decision Was Chosen

The same discipline already applied in BUS-0016: don't build a constraint before a real risk names it, but once named, fix it with a mechanically checkable rule rather than a vague "keep ADRs small" instinct that gets revisited ad hoc and inconsistently.

## Consequences

Easier: future themed ADRs stay reviewable as single decisions — a reader can trust that everything inside one ADR is genuinely one coupled argument, not an accumulating design document. Harder: requires someone to actually notice when a themed ADR's sub-decisions have stopped being coupled, the same enforcement gap BUS-0016 already accepts for file size — not automatic, needs a periodic check.

## Future Implications

If Academic's Organizational Hierarchy cluster (BUS-0017) grows further with a decision unrelated to why Curriculum Specification was introduced, that new decision gets its own ADR rather than being appended to BUS-0017. The same applies to every future themed ADR, starting with whichever domain's v1→v3 retrofit is next.

## Traceability

- **Business requirement:** the Decision Log must remain a set of individually reviewable decisions, not a shadow design document.
- **Introduced in:** post-hoc review of BUS-0017, immediately after it was written.
- **Depended on by:** every future themed ADR.
