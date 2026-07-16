# Administration Platform — Implementation Playbook

**Status:** Frozen 2026-07-14, alongside `docs/adr/0016` through `0022` and `docs/ADMINISTRATION_PLATFORM.md`. This document is the *execution schedule* — it may be re-sequenced sprint by sprint as real work is planned, without requiring a new ADR, provided none of `docs/adr/0022-administration-platform-delivery-principles.md`'s seven binding rules are violated. If a proposed re-sequencing would violate one of those rules, it requires an ADR amendment first, not a Playbook edit.

**Relationship to the main Playbook:** this is a companion document to `docs/IMPLEMENTATION_PLAYBOOK.md`, referenced from a new "Foundation Track: Administration Platform" entry there, the same relationship `docs/ADMIN_PLATFORM_FOUNDATION` work already has to the main Playbook's "Frontend Track F1" entry.

---

## Dependency graph between phases

```
Phase 0 (Formalization)
   |
   v
Phase 1 (Configuration Platform + Developer Enablement)  <- must fully freeze before Phase 2 backend starts
   |
   +--------------------------------------------+
   v                                             v
Phase 2 backend (Provider Registry)   Phase 2 frontend (generic Configuration Workspace)
   |                                             |  (parallel with Phase 2 backend -- the one
   v                                             |   explicit exception to "backend freezes before UI")
Phase 3 (Notification Platform)  <---------------+
   |         \
   |          \ (parallel -- independent modules, no shared schema)
   |           v
   |      Phase 4 (Organization Directory + Access Governance extensions)
   v
Phase 5 (Experience Layer v1)  <---- requires Phase 3 AND Phase 4 frozen
   |         \
   |          \ (parallel -- pure design discussion, no shared capacity)
   |           v
   |      Website design session (not an implementation phase)
   v
Phase 6 (Website Platform)  <---- requires Phase 5 complete AND design session complete
   |
   v
Phase 7 (Advanced Experience Layer: Diff, Wizard, Packages, Rollback, Promotion)

NOT SCHEDULED: Asset & Facility Stewardship / Infrastructure Administration
   -- requires its own dedicated design session before it can even be scoped into a phase
```

---

## Phase 0 — Formalization

**Status: COMPLETE (2026-07-14).** `app/Modules/Settings` (the Sprint 0.1 placeholder) renamed to `app/Modules/Administration`; `deptrac.yaml` gained its own `Administration: [Core]` ruleset entry, deliberately narrower than the generic Foundation entry; `tests/Architecture/AdministrationPlatformBoundaryTest.php` written and proven — both a deliberate cross-module dependency and a deliberate forbidden table-name were introduced, confirmed caught, then removed, restoring a clean baseline. One real bug was found and fixed during that proof: the model-scanning helper's path comparison silently failed to match on Windows (`glob()`'s unresolved `../..` segments never equaled `realpath()`'s fully-resolved, backslash-normalized path), meaning the table-shape check would have passed vacuously instead of catching a real violation — caught only because the negative case was actually run, not assumed. 286 backend tests passing, Pint clean, Deptrac 0 violations.

**Objective:** convert the frozen architecture into binding, reviewable artifacts and a proven CI gate before a single real migration exists.

**Scope — IN:** ADR-0016 through ADR-0022 (complete — see `docs/adr/`); the Administration-Platform-boundary architecture test, written and proven red-then-green against a deliberately-violating dummy migration, the same negative-test discipline already proven for Identity Maintenance's schema scanner (Sprint 3.1).

**Scope — OUT:** any real table, any real API endpoint, any UI.

**Dependencies:** none.

**Deliverables:** the ADR series (done, this freeze); the boundary architecture test, committed.

**Backend responsibilities:** the architecture test only.
**Frontend responsibilities:** none.
**APIs:** none.
**Contracts:** none implemented yet — this phase only fixes their *names* in the ADRs (`DeclaresSettingsSchema`, `DeclaresProviderSlots`, `RegistersReadinessChecks`).
**Services / Registries:** none.
**Background jobs / Events / Caching:** none.
**Security:** none yet.

**Testing strategy:** the boundary test's negative case is this phase's entire testing surface.

**Definition of Done:** every ADR in the series has Status: Accepted; the boundary test exists, has been proven to fail on a deliberate violation, and passes clean on the actual (empty) Administration Platform codebase.

**Risks:** low technical risk, real process risk — this phase being skipped under time pressure is how a project ends up implementing an architecture nobody agreed to in writing.

---

## Phase 1 — Configuration Platform Core *(Minimum Viable Configuration Engine)*

**Status: COMPLETE (2026-07-14).** The Configuration Registry (`configuration_definitions`), the values table (`configuration_values`, keyed by key + altitude + branch, optimistic-locked via `expectedVersion`), and a separate lower-ceremony `configuration_user_preferences` table were built exactly to the 4-shape boundary (ADR-0016). `SettingsResolver::resolve()`/`write()` implement the Global → Branch altitude chain with a `ResolvedSetting` trace, wired into the existing Approval Engine (own, independently-declared `ApprovalRoutingResolver` per Blueprint Addendum B1's promotion-not-prediction rule — not yet a third consumer of IdentityMaintenance's copy) and the existing Audit Engine (full-diff tier). `ConfigurationRegistry::sync()` enforces ADR-0018 Decisions 8-10 (mandatory permission fields, the registration-time integrity heuristic) at sync time, proven against six fixture classes each exercising one guard's negative case. Identity's OTP length/lifetime settings were retrofitted through `IdentityOtpSettings implements DeclaresSettingsSchema`, with the old hardcoded values fully removed from `StepUpAuthenticationService`. All Developer Enablement deliverables exist: `docs/developer/configuration-platform.md`, the shared `registerConfigurationSchemas()` test helper (`tests/Pest.php`), and the `DeclaresProviderSlots`/`ProviderSlotDefinition` Phase 2 scaffold.

Three real implementation bugs were found and fixed during the mandated negative-case proofs, none requiring an architecture discussion: (1) `deptrac.yaml`'s Phase 0 `Administration: [Core]` ruleset never allowed *other* modules to depend ON Administration, only restricted Administration's own outbound dependencies — fixed by adding `Administration` to `Foundation`'s and `Domain`'s allowed-dependency lists, keeping the direction one-way; (2) Spatie's `hasPermissionTo()` throws `PermissionDoesNotExist` rather than returning `false` for a genuinely unseeded permission (a previously-known gotcha, Sprint 3.1) — fixed inside `SettingsResolver::assertCanEdit()` with an explicit catch; (3) `model_has_roles.branch_id` is NOT NULL, so `withTeam(null)` before `assignRole()` fails at the database — fixed by always resolving a real `Branch` in the test helper, mirroring the established `approverUser()` pattern. A fourth issue, Spatie Teams' ambient-team-context drift across multiple test actors, was resolved procedurally (re-asserting `withTeam()` immediately before every permission-sensitive call) rather than by a code fix. 302/302 backend tests passing, Pint clean, Deptrac 0 violations.

**Objective:** prove the Registry + Resolver + Altitude mechanism end-to-end against one real, already-existing consumer, and simultaneously produce the developer conventions every subsequent phase depends on.

**Scope — IN:** the schema-declaration table and manifest loader; the values table, keyed by key + altitude; the `SettingsResolver` service (`resolve(key, scopeContext) → {value, resolvedAtAltitude, trace}`); the Global → Branch → User-Preference altitude chain; wiring into Core's *already-existing* Approval Engine and Audit Engine (no new governance mechanism — this phase proves the hook, does not build a new one); retrofitting Identity's OTP length/lifetime/attempts/resend-delay as the proof consumer, since Identity already exists and needs no new module.

**Scope — OUT:** Provider Registry (Phase 2), any Content Lifecycle concept, Dependency Graph, any Experience Layer item, any UI beyond none.

**Dependencies:** Phase 0 frozen.

### Developer Enablement — part of this phase's Definition of Done, not a separate phase

Before a second real consumer registers into the Configuration Platform, the following must exist, so every future module follows one convention instead of inventing its own:

- **Settings SDK** — the typed helpers a module uses to declare a `DeclaresSettingsSchema` manifest and call `SettingsResolver::resolve()`, so no module hand-rolls resolution logic.
- **Provider SDK** — the equivalent scaffolding for `DeclaresProviderSlots` (Phase 2), built now so Phase 2 consumes it rather than inventing it under its own deadline.
- **Registration helpers** — the deploy-time manifest-loading mechanism itself, exercised by Identity's retrofit, documented as the pattern every subsequent module copies.
- **Testing helpers** — a shared test-harness utility for asserting Resolver correctness (altitude precedence, trace shape, default fallback) without each module re-deriving the same test scaffolding, and the reusable negative-test pattern for the boundary architecture test (Phase 0).
- **Validation helpers** — shared assertion utilities for the metadata model's Validation Rules field.
- **Resolver helpers** — convenience wrappers for the common case (resolve at the current request's branch context) versus the explicit case (resolve at an arbitrary scope, needed by the Experience Layer later).
- **Documentation** — `docs/developer/configuration-platform.md`, written by the end of this phase, mirroring the existing developer-doc convention (`docs/developer/approval-engine.md`, `docs/developer/person-merge.md`): what the contract looks like, why the fifteen metadata fields exist, what's explicitly rejected and why.
- **Developer examples** — the Identity OTP retrofit itself, kept and referenced as the canonical worked example, the same role `PersonTest.php`/`StepUpAuthenticationServiceTest.php` already play as reference patterns elsewhere in this codebase.

**Backend responsibilities:** everything above — this phase is entirely backend.
**Frontend responsibilities:** none, deliberately.
**APIs:** none exposed publicly yet, or a flagged-off internal `GET/PUT /api/v1/settings/{key}`.
**Contracts:** `DeclaresSettingsSchema`.
**Services:** `SettingsResolver`.
**Registries:** the Configuration Registry — first proof of the Registry Pattern (`docs/ADMINISTRATION_PLATFORM.md` §8).
**Background jobs:** none.
**Events:** none new — Configuration writes flow through the existing Audit Engine's logging.
**Caching:** explicitly none — resolve live, per Addendum A6's discipline.
**Security:** a new, narrowly-granted permission gates any Configuration write; the approval-gating hook is wired and tested even though no real `safety_critical` key exists yet.

**Testing strategy:** Resolver correctness (altitude precedence, trace accuracy, default fallback); the Phase 0 boundary test, now exercised against a real, growing schema; an end-to-end test confirming `StepUpAuthenticationService` genuinely reads OTP parameters through the Resolver, with the old hardcoded values fully removed, not duplicated.

**Definition of Done:** full test suite green, Pint/Deptrac clean; Identity's OTP settings live through the Configuration Platform; every Developer Enablement deliverable above exists and is documented; ADR-0018 marked Accepted-and-implemented.

**Risks — highest in this roadmap.** A schema mistake here is expensive across every independent customer deployment (ADR-0006's dedicated-instance model means a coordinated migration across many separate databases, not one shared fix). Do not compress this phase's timeline to hit a date.

---

## Phase 2 — Provider Registry & Credential Vault, with the first UI in parallel

**Status: COMPLETE (2026-07-16), backend only — frontend Configuration Workspace deferred, not scoped by the user for this pass.** `provider_registrations` (the Registry Pattern's second instance, ADR-0018 Decision 7) and `provider_credentials` (the Vault -- always versioned, `credentials` stored via Laravel's `encrypted:array` cast, cheap who/when-only audit tier per ADR-0019 Decision 5) were built exactly to ADR-0016 §4's boundary, structurally parallel to Phase 1's Configuration tables but never touching them. `ProviderRegistry::sync()` mirrors `ConfigurationRegistry::sync()`'s discipline (mandatory edit-permission field, approval-permission-when-required, and a reflective capability-contract-implements check that stays inside Administration's `deptrac` boundary since it never statically imports the module-owned interface it checks). `ProviderCredentialVault` mirrors `SettingsResolver`'s trace-returning altitude chain and optimistic-locking write contract as an independent implementation (Blueprint Addendum B1's promotion-not-prediction rule — second consumer of the write-contract shape, not yet a third). `ProviderManager::resolve()` is the Manager-pattern dispatch ADR-0019 names: a slot key resolves to a container-instantiated Provider purely from the Registry row, with no vendor-name conditional anywhere. `HealthCheckRunner` invokes a resolved Provider's `HealthCheckable::healthCheck()` (a small Core interface added during this phase, additive to the Phase 1 `ProviderSlotDefinition` scaffold — see below) with a short-TTL cache, the first legitimate caching need in this roadmap.

Media's disk-tier selection is the mandated retrofit: `R2StorageProvider` self-registers a `media.storage.r2` slot (key/secret/region/endpoint — bucket names stay plain env-sourced config, never a credential field), and `App\Providers\AppServiceProvider::configureMediaStorageCredentials()` injects the Vault's resolved values into `config/filesystems.php`'s `public`/`private`/`temporary` disks at boot time, for any tier whose driver is `s3` — the old `env('R2_ACCESS_KEY_ID')`-style lines are fully removed from the config file itself.

Three fundamentally different proof providers were implemented end-to-end (declare → sync → write real credentials through the Vault → the Provider uses them), validating genericity per this phase's own explicit exit test: **SmtpEmailProvider** (host/port/username/password/encryption, sends via a real `SmtpRelayMail` Mailable dynamically configured per-request), **GoogleOAuthProvider** (client_id/client_secret, a real `Http::fake()`-proven token exchange against `oauth2.googleapis.com`), and **FirebasePushProvider** (an FCM v1 service-account triple including a multi-line PEM `private_key`, proving the encrypted column round-trips more than short tokens, `Http::fake()`-proven against `fcm.googleapis.com`). All three, plus the Media retrofit, registered and synced together with zero mutual interference and four genuinely distinct credential shapes.

Two real implementation gaps were found and fixed during this phase, neither requiring an architecture discussion: (1) Phase 1's `ProviderSlotDefinition` scaffold had no permission/approval-gating fields at all (it predated any real Vault write path) — fixed additively, mirroring `SettingDefinition`'s identical fields, since nothing in Phase 1 had consumed the VO yet; (2) `Mail::raw()` is a silent no-op inside Laravel's `MailFake` (it only records real `Mailable` instances), discovered when `Mail::assertSentCount()` returned 0 against a genuinely-sent message — fixed by giving `SmtpEmailProvider` a real `SmtpRelayMail` Mailable instead of `Mail::raw()`.

Verification: 338/338 backend tests passing (36 new), Pint clean, Deptrac 0 violations. A real dual-MariaDB-connection concurrency test (`ProviderCredentialLockingConcurrencyTest`) proves the Vault's row lock genuinely blocks a second writer, mirroring `ConfigurationValueLockingConcurrencyTest`. Negative-case proofs cover: every `ProviderRegistry::sync()` guard, every `ProviderCredentialVault::write()` guard (permission, credential-shape, optimistic-locking conflict), a secret never appearing in the model's array/JSON representation, a secret never appearing in the Activitylog `properties` payload (cheap-tier audit proven directly), and the credential column never containing plaintext at rest.

**Deferred, not abandoned:** the generic Configuration Workspace frontend (this phase's own stated "one exception to backend-freezes-before-UI" parallel track) was not built in this pass — the user's Phase 2 instruction scoped this pass to backend implementation and the three-provider genericity proof; frontend work resumes when explicitly requested.

**Objective:** prove the Provider mechanism against one real, low-stakes vendor category; prove the frontend can consume Phase 1's now-frozen API.

**Scope — IN (backend):** `DeclaresProviderSlots` contract (built on Phase 1's Provider SDK); encrypted credential storage; the health-check callback contract; retrofitting Media's existing disk-tier selection as the first real Provider.
**Scope — IN (frontend, parallel track — the one exception to "backend freezes before UI"):** a generic Configuration Workspace, registered through the already-proven `WorkspaceDefinition` extension point (`v1.0-admin-platform-foundation`), using the already-built DataTable and Form frameworks unmodified.

**Scope — OUT:** any business-specific Provider (arrives with its consuming capability, Phase 3+); any Experience Layer feature.

**Dependencies:** Phase 1 **frozen** (ADR-0022 §1 — the one hard sequential dependency in this roadmap).

**Deliverables:** the Provider Registry service; Media's disk selection live through it; a working, generic Configuration Workspace.

**Backend responsibilities:** the vault, the synchronous health-check runner, the Media retrofit.
**Frontend responsibilities:** the generic Configuration Workspace.
**APIs:** `GET /api/v1/settings` (list, filterable by capability), `PUT /api/v1/settings/{key}`, `GET /api/v1/providers` (list slots + health).
**Contracts:** `DeclaresProviderSlots`.
**Services:** Provider Registry, Health-Check Runner v1 (synchronous).
**Registries:** Provider Registry — second proof of the Registry Pattern, first proof it composes cleanly with an existing module rather than only greenfield ones.
**Background jobs:** none yet — health checks are on-demand.
**Events:** none new.
**Caching:** provider health-check results, short TTL, invalidated on next check — the first legitimate caching need in this roadmap.
**Security:** credential fields are the first genuinely encrypted data here; a distinct, narrower permission gates them versus generic Configuration access.

**Testing strategy:** Provider Registry contract tests; a negative test proving credentials never appear unmasked in any API response or log line; the Configuration Workspace's first Vitest coverage, extending the existing harness from `v1.0-admin-platform-foundation`.

**Definition of Done:** Media's storage selection is live through the Provider Registry with old hardcoded config removed; an administrator can open the Configuration Workspace and see/edit both Identity's OTP values and Media's disk selection through one real UI; ADR-0019 marked Accepted-and-implemented for this slice.

**Risks:** moderate — the main risk is UI scope creep into business-specific screens before Phase 1/2's backend is fully hardened; the Workspace stays generic in this phase, no exceptions.

---

## Phase 3 — Notification Platform, real implementation

**Objective:** implement ADR-0012/0013 for real — currently architecture-only — on top of Phase 1+2.

**Scope — IN:** Channel/Provider architecture for Email/SMS (start with one vendor each, not all), registered into Phase 2's Provider Registry; routing rules and templates as Administration-layer data using the Content Lifecycle Pattern; the first real, business-shaped Workspace.
**Scope — OUT:** Automation Rules / Topic Messaging — requires the ADR-0012 amendment flagged during this review, not silent scope absorption; the Operations-layer send/delivery pipeline is a separate, larger build, out of this phase.

**Dependencies:** Phase 1 and Phase 2 both frozen.

**Backend responsibilities:** Channel contracts, first real vendor Providers, template schema, routing-rule schema.
**Frontend responsibilities:** the Notifications Workspace — provider selection, template list/editor, routing rules.
**APIs:** `GET/POST /api/v1/notifications/providers`, `GET/POST /api/v1/notifications/templates`, `GET/PUT /api/v1/notifications/routing`.
**Contracts:** Channel/Provider contracts per ADR-0013.
**Services:** template renderer (schema/validation only at this layer).
**Registries:** Notification Provider slots, registered into Phase 2's Provider Registry — not a separate registry.
**Background jobs / Events:** none required at the Administrative layer.
**Caching:** template/routing reads, same short-TTL pattern as Phase 2's health checks.
**Security:** template-content editing gets its own permission, distinct from provider-credential editing — first real exercise of per-key permission granularity.

**Testing strategy:** contract tests per Channel; a negative test proving a template can't reference an undeclared merge-tag (Validation Rules, exercised for the first time).

**Definition of Done:** an administrator configures a real SMS provider, authors a real template, and sets a real routing rule through the Notifications Workspace, resolvable through Phase 1's Resolver exactly like any other Configuration.

**Risks:** moderate — hold the line against Automation Rules scope bleed before the ADR-0012 amendment exists.

---

## Phase 4 — Organization Directory & Access Governance extensions *(parallel with Phase 3)*

**Objective:** two independent, low-risk extensions to already-existing modules, scheduled alongside Phase 3 because they touch entirely different code — the first proof this roadmap parallelizes safely (ADR-0022 §6).

**Scope — IN:** Organization growing real Contacts/Addresses/Working-Hours/Emergency-Contacts child entities using the already-existing Address/Phone/Contact value objects (Blueprint §5) — no new Foundation module; Identity's machine/API identity and Federation-provider registration (SAML/OIDC/SCIM).

**Dependencies:** Phase 1 only.

**Backend responsibilities:** the child-entity tables (existing VOs), the Federation-provider contract.
**Frontend responsibilities:** an Organization Profile Workspace section — real CRUD, not Configuration edit forms.
**Testing strategy:** identical shape to Person's own Contacts/Addresses testing (§4 precedent) — no new pattern invented.

**Definition of Done:** Organization's directory data exists as real, validated, audited rows, consumed by any Workspace via a normal read API, never through the Configuration Registry.

**Risks:** low — largely mechanical extension of already-proven patterns.

---

## Phase 5 — Experience Layer v1 *(gated on Phase 3 + Phase 4)*

**Objective:** the first slice of `docs/adr/0021-administration-experience-layer.md` — only once real content exists to build it against.

**Scope — IN:** Global Search (Addendum D5, indexing Phase 1-4's registrations); Missing Configuration Detection (exercising `required: bool` for the first time against real unset-but-required keys); Readiness Checks (`RegistersReadinessChecks`, first real custom check: Notifications' own "at least one Email provider configured"); the Resolution Trace surfaced in the UI.
**Scope — OUT:** Diff, Wizard, Packages, Snapshots, Import/Export, Rollback, Environment Promotion — Phase 7, deferred until there is more operational history and more registered capabilities to justify them.

**Dependencies:** Phase 3 and Phase 4 both frozen (ADR-0022 §4 — the Experience Layer has nothing to derive from before then).

**Parallel opportunity:** Website's dedicated design session should run *during* this phase (ADR-0022 §7) — pure discussion, zero shared implementation capacity, so Phase 6 can start the moment Phase 5 finishes.

**Backend responsibilities:** the Dependency Graph compiler; the Health/Readiness engines.
**Frontend responsibilities:** search wired into the existing command palette (`v1.0-admin-platform-foundation`); a Readiness indicator on the Notifications Workspace.
**Testing strategy:** the same negative-test discipline as every prior phase — deliberately mis-register a required key with no value, confirm detection catches it.

**Definition of Done:** an administrator can search for a setting, see why a value resolved the way it did, and see whether Notifications is "ready," with a real reason if not.

**Risks:** low-to-moderate — the primary risk is building Score without the classification-weighting and drill-through discipline ADR-0021 §6 requires; hold that line explicitly.

---

## Phase 6 — Website Platform *(gated on its own design session)*

**Objective:** the first Digital Experience Delivery surface, once its own architecture session (held during Phase 5, not specified by this Playbook) has produced a real scope.

**Dependencies:** Phase 5 complete; the Website design session complete.

**Fixed regardless of that session's outcome:** consumes Phase 1 (Configuration) and Phase 2 (Provider Registry, for analytics/tracking credentials) exactly as every prior phase; Content (Pages/Menus) uses the Content Lifecycle Pattern already named, not a new one.

**Risks:** the highest risk in this phase is starting implementation before the design session has actually happened — flagged across four separate rounds of this architecture review as the most avoidable mistake available.

---

## Phase 7 — Advanced Experience Layer

**Objective:** Diff, Wizard, Packages, Snapshots, Import/Export, Rollback, Environment Promotion.

**Dependencies:** at minimum Phases 3, 4, and 6 frozen. Rollback needs real audit history spanning real changes to be meaningful. Import/Export needs at least two real capabilities to prove cross-capability serialization. Environment Promotion needs a second real deployment to promote *to*.

**Definition of Done:** an administrator can export a Package, dry-run-import it into a fresh deployment, see every conflict before committing, and confirm — the full loop from ADR-0021 §9, working end to end.

**Risks:** moderate technical risk, real *temptation* risk — this is the most impressive-sounding phase and the most likely to get pulled forward under stakeholder pressure ahead of its prerequisites. Resist it; every item here is a derived layer with nothing to derive from until the phases before it are real.

---

## Not scheduled

**Asset & Facility Stewardship / Infrastructure Administration** does not appear as a numbered phase, deliberately. It requires its own dedicated design session — flagged repeatedly across this entire review — before it can even be scoped into a phase, let alone estimated. Treated exactly like Maintenance and CRM elsewhere in this project: named, acknowledged, explicitly deferred, never speculatively started.

---

## Summary: risk, foundation, parallelization

**Highest risk:** Phase 1 — mistakes here are expensive across every independent customer deployment, not one shared database.
**Most foundational:** Phase 1, then Phase 2.
**Must never be parallelized:** Phase 1 and Phase 2 backends (ADR-0022 §1); the Experience Layer must never run ahead of the capabilities it derives from (ADR-0022 §4).
**Safe and encouraged to parallelize:** Phase 3 alongside Phase 4; Website's design session alongside Phase 5's implementation.

## References

`docs/ADMINISTRATION_PLATFORM.md`. `docs/adr/0016` through `0022`. `docs/IMPLEMENTATION_PLAYBOOK.md` (Frontend Track F1, the direct precedent for this document's own relationship to the main Playbook). `docs/developer/person-merge.md` (the dry-run convention Phase 7 reuses).
