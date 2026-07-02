## What does this PR do?

<!-- One or two sentences. Link the sprint/task from docs/IMPLEMENTATION_PLAYBOOK.md. -->

## Quality Gates

Every box must be checked before this PR can merge — see `docs/IMPLEMENTATION_PLAYBOOK.md` → Global Engineering Discipline.

- [ ] All automated tests green (unit, feature, architecture)
- [ ] Larastan — zero new errors
- [ ] `deptrac` — zero module-boundary violations
- [ ] Pint — clean
- [ ] No direct cross-module Eloquent access introduced
- [ ] Documentation updated (API docs / developer docs / CHANGELOG as applicable — see the Documentation Discipline table in the Playbook)
- [ ] `CHANGELOG.md` entry added
- [ ] If this PR touches anything on the Blueprint's frozen list (`docs/DOMAIN_BLUEPRINT.md` Addendum A8) — a linked, approved ADR is attached. If not applicable, check this box to confirm you verified that.
- [ ] Reviewed by someone other than the author (or, on a solo pass, a 24-hour self-review against this checklist before merge)

## Frozen-decision check

- [ ] This PR does **not** change anything in `docs/DOMAIN_BLUEPRINT.md`. If it does, ADR link: ___
