# BUS-0014: Content Authoring Workflow — Proposed, Not Yet Decided

**Status:** 🟡 Proposed — a finding and its reasoning, not an accepted decision.

**Date:** 2026-07-22

**Related Domains:** Learning

**Related ADRs:** BUS-0001 (Course Template versioning — the object this workflow's states would apply to)

## Context

Identified during the same discovery pass: an explicit draft → review → approval → publish workflow for Course Template content, distinct from an Offering's own lifecycle. Content Author and Reviewer already exist as named Course Staff roles (BUS-0011), but no workflow describing what those roles actually do was ever specified.

## Problem

Should AlphaSchool build an explicit content review/approval workflow, and if so, in what phase?

## Alternatives Considered

Not applicable — identified and prioritized, not yet decided.

## Final Decision

**None yet.**

## Why This Decision Was Chosen

N/A.

## Consequences

None yet, by design.

## Future Implications

If accepted: this would likely reuse the platform's existing Approval Engine (Core Platform Service) rather than inventing a new one, consistent with the reuse discipline applied everywhere else in this document — that reuse decision itself is not yet made either.

## Traceability

- **Business requirement:** Content Author and Reviewer roles already exist in the accepted Course Staff model (BUS-0011); having the roles without the process they operate in is a concrete, not speculative, gap.
- **Introduced in:** the "Forget Moodle, Forget Canvas" discovery turn, rated Important priority.
- **Depended on by:** nothing yet.
