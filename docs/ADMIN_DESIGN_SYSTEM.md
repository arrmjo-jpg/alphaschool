# AlphaSchool ERP — Administration Frontend Design System

**Status: FROZEN / OFFICIAL — adopted 2026-07-16.** This is the sole design-system reference for building the AlphaSchool ERP Administration frontend, carrying the same governance weight as `docs/ADMIN_PLATFORM.md` and `docs/DOMAIN_BLUEPRINT.md`: no redesign without a real implementation problem, resolved by the smallest possible documented amendment (see §22). No implementation code has been written against it yet; implementation begins per §15/§22.

**Scope.** This document reverse-engineers the visual identity, UX language, and component behavior of the legacy admin (`C:\Users\user\Downloads\alqla-main\alqla-main\admin-frontend`, hereafter **Old Admin**) and specifies how that identity should be rebuilt on top of the already-frozen Admin Platform Foundation (`docs/ADMIN_PLATFORM.md`, ADR-0015; hereafter **New Admin**, the `admin/` directory in this repo). No Old Admin code, business logic, or routing is reused. Only visual language, interaction patterns, and — critically — **user journeys** are preserved. Everything else is new architecture, already built: React 19, TanStack Router/Query/Table, Tailwind v4, Radix UI, React Hook Form, Zod, a Workspace-based information architecture (ADR-0015), and, as of Phase 2, a live Administration Platform backend (Configuration Platform, Provider Registry, Credential Vault).

**Method.** Every claim below is sourced from reading actual files in both codebases — not inferred from framework conventions. Where a finding is a judgment call rather than an observed fact, it is labeled as a recommendation, not a fact.

---

## 0. Executive Summary

Old Admin is a mature, disciplined, RTL-first Arabic/English CMS admin with a consistent (if quietly self-contradictory) visual identity: a teal brand color, a bilingual Tajawal/Inter font stack, a deliberately flat "no rounded corners anywhere" policy that is in practice violated by nearly every interactive component (buttons, inputs, modals, dropdowns all use `rounded-xl`/`rounded-2xl`), and a calm, low-motion interaction language (`transition-colors` almost everywhere, no scale/translate hover effects). It has real, working CRUD, settings, and system-operations screens with generally good bones — but it also has a working, quantifiable set of UX gaps: a **decorative non-functional search box**, a **stub notifications button with no data source**, **zero unsaved-changes protection anywhere**, **zero settings search/discoverability**, and **duplicated, non-componentized markup** for patterns (sticky save bars, status Row/Card, StatusPanel-shaped feedback states) that appear identically in 3+ places.

New Admin currently has none of this visual identity — it ships the generic shadcn/ui grayscale default theme, system-ui font, and a flat single-level Workspace navigation model with zero business workspaces registered (by design, per ADR-0015 Decision 2: infrastructure-only milestone). It does, however, already have the *right bones* for most of what Old Admin's visual identity needs: a token-driven `@theme` system in `index.css` that Old Admin's brand tokens map onto almost 1:1, and a `--radius` scale that already lands close to the "subtle 6–8px" the user asked for — meaning the radius fix is not a new decision, it's a matter of *not fighting* what's already there, unlike Old Admin's own self-contradiction.

The single largest architectural gap between the two information architectures: Old Admin's navigation is a static, hardcoded two-level tree (`NavSection[]` with collapsible groups); New Admin's navigation is a registry-driven, **flat, single-level** list of self-contained Workspaces. The user's requested new IA (`Administration` as a parent of nine children workspaces, sitting alongside many flat top-level items) needs SideNav to grow a **grouping** concept it does not have today — a small, additive change, not a redesign, and one this document specifies precisely in §8.

---

## 1. Journey-Based Analysis

Per explicit instruction, this section analyzes Old Admin as journeys, not screens. Every sub-journey below states what exists today, cites the file, and separates *fact* from *judgment*.

### 1.1 First Impression Journey

**Login → Loading → First Dashboard → Empty Workspace**

- **Login** (`features/auth/pages/LoginPage.tsx`, `LoginForm.tsx`): split-screen layout — `AuthLayout.tsx` is `grid min-h-screen lg:grid-cols-2`, left column `AuthCover.tsx` (brand-primary solid background + radial-gradient overlay + a per-route SVG illustration + translated title/subtitle), right column a centered `max-w-sm` form. Recaptcha v2 fallback UI is conditionally rendered inline. Simple two-field form (email, password), forgot-password link, submit button that swaps its own label to a "submitting…" string while pending (no separate spinner element).
- **Fact — mobile has no brand cover at all.** `AuthCover` is `hidden ... lg:flex` — below the `lg` breakpoint (1024px) a user's *first ever impression* of the product is a bare, unbranded, centered form with no illustration, no color, no identity. This is not a graceful degradation; it is a full feature loss on every phone and most tablets.
- **Loading**: `AdminLayout.tsx` shows a single centered `LoadingState` (spinning `Loader2`) while auth status resolves — clean, but it's the *only* loading treatment in the entire first-impression path; there is no skeleton of the eventual layout (no "shell first, content streams in" pattern).
- **First Dashboard** (`features/dashboard/pages/DashboardPage.tsx`): every widget (`SiteKpis`, `RecentContent`, `PendingModeration`, `CacheControls`, `ServerStatus`) is self-contained — fetches its own data, checks its own permission, silently returns `null` if unauthorized. **Fact**: the dashboard page itself performs no top-level permission check; `/` is reachable by any authenticated admin and sections self-hide. For a brand-new user with few permissions, first impression could legitimately be a near-empty page with just `QuickActions` and nothing else — there is no "here's what you can do" onboarding framing for that state, just silence.
- **Empty Workspace**: Old Admin has no equivalent concept (it is not workspace-based) — the closest analogue is a permission-filtered dashboard that renders almost nothing. New Admin already has a *purpose-built* `EmptyWorkspaceState` component (part of the already-frozen Admin Platform Foundation) that Old Admin never needed to solve — this is a case where New Admin's existing infrastructure is already ahead, not behind.

### 1.2 CRUD Journey

**List → Search → Filter → Details → Create → Edit → Delete → Success**

Traced end-to-end through `features/user-management/{pages/UsersPage.tsx, pages/UserFormPage.tsx, hooks.ts}` as the most complete real CRUD flow in the codebase.

- **List**: `DataTable` + `Pagination` + `SearchInput`, page state lives in the URL (`useSearchParams`) — good, deep-linkable, refresh-safe. Loading state fades the table to `opacity-70` rather than blanking it (`transition-opacity duration-200`) while a background refetch (e.g. after a filter change) is in flight — a good, low-jank pattern.
- **Search**: `SearchInput` debounces client-side (350ms) before writing to the URL — server does the actual filtering. No "searching…" affordance during the debounce window itself, only once the request fires (covered by the table's own opacity dim).
- **Filter**: four independent native `<select>` dropdowns (account type, status, role, trashed) laid out in a flat `flex flex-wrap` toolbar row. **Judgment**: functionally fine, but there is no "N filters active" indicator, no one-click "clear all filters," and no saved/named filter concept — a user with 3 filters active has no visual summary of that state beyond re-reading each dropdown's current value.
- **Details**: Old Admin has **no dedicated detail/view page** for Users — clicking a row's actions opens an edit form directly, or a dropdown menu of point actions (reset password, verify email, delete). There is no read-only "view" state distinct from "edit" state anywhere observed in this flow.
- **Create / Edit**: `UserFormPage.tsx` — a single, long, section-carded form (`Panel` wrapper, 6 sections: Profile/Basic/Security/Bio/Social/Roles) used for **both** create and edit via one `isEdit` boolean, including the password fields (edit mode just relabels "Password" to "Leave blank to keep current"). Client validation failure triggers **both** an inline per-field error **and** a toast (`toastError`) — a deliberate double-signal, not a bug. Server-side 422 validation errors are mapped back onto the correct RHF field (`applyServerErrors`) rather than shown as a generic banner — a good pattern.
- **Fact — no unsaved-changes protection.** Nothing in `UserFormPage.tsx` checks `formState.isDirty` before navigation; clicking "Back to list" or any sidebar link while mid-edit discards all typed changes with zero warning, zero confirmation. Confirmed absent by direct code inspection, not assumed.
- **Delete**: routed through `useToast().confirm()` (a SweetAlert2 modal, not the app's own `Modal` component — see §Technical Debt) — title/text/confirm-label/cancel-label, returns a boolean. Used identically for delete, restore, password-reset, and email-verify — i.e., **every** destructive-or-sensitive action gets the same generic yes/no gate, with no differentiation by risk level (deleting a user and verifying an email use the exact same dialog weight).
- **Success**: on successful create/update, the only feedback is a `navigate(paths.users)` — an implicit "you're back on the list, so it worked" signal, not an explicit success toast. (Delete/restore/reset actions, by contrast, likely do toast — success feedback is inconsistent across actions within the same page.)

### 1.3 Settings Journey

**Navigation → Discoverability → Search → Categories → Save → Validation → Unsaved Changes**

Traced through `features/settings/{components/SettingsNav.tsx, components/SettingsSection.tsx, components/SaveBar.tsx, pages/GeneralSettingsPage.tsx}`.

- **Navigation**: `SettingsNav` is a static, hardcoded list of 7 items (General, Branding, Email, Social, Analytics, Media Storage, Newspaper), rendered as `NavLink`s in a bordered panel — horizontally scrollable on mobile (`flex gap-1 overflow-x-auto ... lg:flex-col`), vertical tabs on desktop.
- **Discoverability / Search**: **Fact — there is no search of any kind inside Settings.** No search box, no fuzzy-match, no keyboard shortcut. Seven categories is manageable by eye today; it will not scale to the dozens of Configuration Platform keys the new architecture is designed to support (ADR-0018's whole registration model assumes potentially hundreds of declared keys across every module).
- **Categories**: one page per category, one form per page (`GeneralSettingsPage` alone has 6 `SettingsSection` cards covering site identity, contact, footer, controls, and map coordinates — a lot of unrelated concerns sharing one page and one save action).
- **Save**: each settings page has its own independent `<form>` and its own `SaveBar` — there is no unified "you have pending changes across N settings pages" concept, by design (each page saves independently). `SaveBar` correctly disables the Save button and shows a permission-denial note when the user lacks `settings.edit`, rather than hiding the fields entirely — a good, honest "you can look, you can't touch" pattern worth keeping.
- **Validation**: Zod schema + RHF, same inline-error pattern as the CRUD forms. Fine.
- **Unsaved changes**: **Fact — identical gap to the CRUD journey, confirmed directly in `GeneralSettingsPage.tsx`.** No `isDirty` check, no route-change guard. Clicking any other item in `SettingsNav` while mid-edit — a single, un-confirmed click — silently discards everything typed. Worse than the CRUD case because Settings pages are long (6 sections, ~20 fields on the General page alone), so the amount of work at risk per accidental click is larger.
- **A second, more severe finding in this journey**: `GeneralSettingsPage.tsx` includes a **`maintenance_mode` toggle** — an action that takes the entire public site offline — as one `SwitchField` among ~20 other benign fields on the same form, saved by the exact same "Save" button with the exact same lack of ceremony as changing a copyright string. There is no elevated confirmation for this specific field, even though the same codebase clearly has a `confirm()` mechanism it uses for far lower-stakes actions elsewhere (e.g. "restore a user").

### 1.4 Administrative Journey

**Notifications → Audit → Background Jobs → Health → Integrations**

- **Notifications** (`layouts/components/NotificationsButton.tsx`): **Fact, confirmed by an explicit code comment** — this is a structural stub. The Arabic comment reads (translated): *"purely a structural/skeleton notifications element — no notifications endpoint in scope."* The dropdown always renders an `EmptyState`. There is no real notification data anywhere in the admin's notification affordance.
- **Audit** (`features/user-management/pages/ActivityLogPage.tsx`): genuinely solid — filterable by module/event/date range, each row expandable to show an old→new attribute diff (`<s>` strikethrough old value → new value) plus free-form context key/values, all forced `dir="ltr"` correctly since diffs are technical data. This is the strongest single page in the Administrative Journey and a good pattern to carry forward almost as-is.
- **Background Jobs** (`features/system/pages/FailedJobsPage.tsx`): real bulk-action UX — per-row and select-all checkboxes, bulk retry/delete, "retry all"/"delete all" (both gated behind `confirm()`), server-truncated exception text with a native `title` tooltip for the full message. A reasonable, working pattern.
- **Health** (`features/system/pages/DiagnosticsPage.tsx`): a manual-refresh (no auto-poll, no live-updating) dashboard of environment info, driver names, and boolean-badge connectivity checks (`okBadge`/`boolBadge` helpers), plus a manual "clear content cache" destructive action (correctly gated behind `confirm()`, and correctly notes in its own copy that the action is audited).
- **Integrations**: Old Admin has a flat `thirdParty`/`cdn` settings page (`features/third-party`, `features/cdn` — not read in full detail for this pass, referenced via nav config) rather than any Provider-Registry-shaped concept — there is no equivalent in Old Admin to what New Admin's backend now actually has (Phase 2's Provider Registry + Credential Vault: multi-provider-per-capability, health checks, credential sensitivity). Old Admin's Integrations journey is strictly less sophisticated than what the new backend already supports; the frontend gap here is the opposite of most of this document — New Admin's *backend* has already outgrown what Old Admin's *frontend* ever needed to represent.
- **Cross-cutting observation**: `DiagnosticsPage`'s `Row`/`Card` pair and `FailedJobsPage`'s bulk-toolbar pattern are each hand-rolled once per page rather than shared — see Technical Debt (§17).

### 1.5 Mobile Responsiveness Journey

- Sidebar collapses to a full-screen drawer with backdrop-blur below `lg` (not a persistent icon rail — Old Admin has no "collapsed-but-visible" state on mobile, only "hidden" or "full overlay").
- Auth cover disappears entirely below `lg` (§1.1 — a real feature loss, not graceful degradation).
- The Topbar's decorative search "button" is hidden below `md` — meaning the one piece of UI that looks most like global search doesn't even *pretend* to exist on mobile.
- Toolbars (filter rows, bulk-action rows) consistently use `flex flex-wrap` / `flex-col sm:flex-row`, which is a functional but visually unrefined mobile pattern — filters wrap into a ragged multi-line block rather than collapsing into a "Filters" disclosure the way a more considered mobile-first table toolbar would.
- Tables have no card/list fallback view for narrow screens — `DataTable`'s only mobile accommodation is `overflow-x-auto` on the table container, i.e. horizontal scroll. On a phone, the Users table (6 columns including an avatar/name compound cell) is genuinely difficult to use.
- **Judgment**: none of this is broken, but "responsive" here means "does not visually break," not "is designed for," which is a meaningfully lower bar.

### 1.6 Accessibility Journey

**What's good, confirmed by direct inspection:**
- Consistent `aria-invalid` wiring from RHF field state into styled `aria-[invalid=true]:` Tailwind variants (not a separate visual "error" prop needing manual sync).
- `aria-label` present on icon-only buttons (theme toggle, language switch, chat/contact/notification bells, mobile menu trigger).
- `aria-expanded` on the collapsible sidebar group buttons, `aria-current="page"` on the active nav link.
- A consistent `focus-visible:ring-2 focus-visible:ring-ring` treatment across buttons, inputs, dropdown items — never relying on the browser's unstyled default outline, and correctly using `:focus-visible` (keyboard-only) rather than `:focus` (which would ring on every mouse click too).

**What's missing or inconsistent, confirmed by direct inspection:**
- **No skip-to-content link** anywhere in `AdminLayout.tsx` — a keyboard/screen-reader user must tab through the entire sidebar on every single page before reaching main content.
- **No `aria-live` region** for toast notifications (SweetAlert2 toasts are visual-only by default configuration; nothing in `useToast.ts` adds `role="status"`/`aria-live="polite"`) — a screen-reader user gets no announcement that a save succeeded or failed.
- **The decorative Topbar search button is a genuine accessibility anti-pattern**, not just a UX one: it is a focusable, `aria`-unlabeled-as-decorative element that visually and semantically presents as a functional search input/button to any assistive technology, but does nothing when activated. A screen-reader user has no way to distinguish it from real search.
- **Password-visibility toggle uses `tabIndex={-1}`**, deliberately removing it from the tab sequence — defensible (keeps tab order moving straight from password field to next field) but means a keyboard-only user cannot reveal the password without a mouse, an inconsistency worth a deliberate decision either way rather than an unstated default.
- **Custom controls built without Radix** (`SwitchField`'s hand-rolled `<button role="switch" aria-checked>`, `Modal.tsx`'s hand-rolled dialog) each re-implement keyboard/focus-trap behavior independently rather than inheriting it from one audited primitive — real risk of subtle divergence (e.g., does the hand-rolled `Modal` trap focus? Does Escape work identically to Radix's own Escape handling? Not verifiable without a dedicated a11y audit, which is itself the finding: unaudited custom interactive primitives are a standing risk.)

### 1.7 RTL Journey

**What's good, confirmed by direct inspection:**
- Pervasive, correct use of Tailwind logical properties (`ps-`/`pe-`, `start-`/`end-`, `border-e`, `ms-auto`) rather than physical `left`/`right`/`ml`/`mr` — this is the single most consistently well-executed pattern in the entire codebase.
- Deliberate, correct `dir="ltr"` overrides on genuinely LTR content inside an RTL page: phone numbers, dates, email fields, exception stack traces, activity-log diff values, URLs — checked in at least six separate files, always applied precisely to the LTR-content span, never to a whole page or section.
- SweetAlert2 toast position swaps `top-start`/`top-end` based on resolved direction, keeping toasts anchored to the reading-start corner in both languages.
- The `RangeFilter`'s native date inputs are correctly forced `dir="ltr"` even inside an otherwise-RTL analytics panel, since calendar dates read left-to-right by convention regardless of UI language.

**What's inconsistent, confirmed by direct inspection:**
- `Pagination.tsx` swaps chevron icons via a dual-render `rtl:hidden`/`ltr:hidden` pair (two icons, one hidden by direction) rather than a single icon with a `rtl:rotate-180` transform — works, but is double the markup for the same visual result, and is inconsistent with the `rtl:rotate-180` pattern used elsewhere in the same codebase (e.g. `UserFormPage`'s "back to list" arrow).
- No `[dir]`-scoped visual regression testing evidenced anywhere (no Storybook, no RTL snapshot tests) — RTL correctness today is a product of careful authorship, not a verified, protected invariant.

---

## 2. UX Mistakes Inventory

Each entry: the problem, why it's a problem, who is affected and how, and the concrete fix for AlphaSchool ERP.

### M1 — Decorative, non-functional search button in the Topbar
**Problem.** `Topbar.tsx` renders a styled element that looks exactly like a search input (icon, placeholder text, input-shaped border/background) but has no `onClick`, no state, does nothing when interacted with.
**Impact.** Every user's very first instinct on any admin dashboard — "let me search for X" — fails silently. There is no error, no redirect, nothing: the UI simply doesn't respond, which reads as *broken*, not *absent*. This is worse for trust than not having the element at all.
**Fix.** New Admin already has a real `SearchBar` component wired to `SearchProvider` and a real Command Palette (`⌘K`). Never ship a visual affordance without the mechanism behind it — if a capability isn't ready, omit the control entirely rather than fake it.

### M2 — Stub notifications with a hardcoded empty state
**Problem.** `NotificationsButton.tsx` is explicitly documented in its own source comment as a stub with no backing endpoint.
**Impact.** Same trust erosion as M1, compounded: the bell icon has a badge-slot ready to show an unread count, priming the user to expect real notifications, then delivering nothing every single time.
**Fix.** New Admin's `notification-center.tsx` is already wired to a real `use-notifications` hook and query. Ship it only once there is a real notification source (the backend's Notification Engine, or Administration Platform events), not before.

### M3 — Zero unsaved-changes protection, anywhere
**Problem.** Confirmed absent in both the CRUD form (`UserFormPage.tsx`) and every Settings page (`GeneralSettingsPage.tsx` and, by pattern, its siblings). No `isDirty` check, no navigation guard, no `beforeunload` handler.
**Impact.** Silent data loss on the single most common accidental action in any admin UI: clicking a nav link while mid-form. The longer the form (Settings pages run 15–20 fields), the larger the loss per incident.
**Fix.** A router-level "confirm navigation" guard (TanStack Router supports `onBeforeUnload`/blocking navigation via a shared hook) wired to RHF's `formState.isDirty`, reused by every form in the new admin — this belongs in the Form platform layer (`platform/forms/`), not per-page.

### M4 — A destructive, site-wide action (`maintenance_mode`) has the same UI weight as a cosmetic field
**Problem.** The maintenance-mode toggle lives inside a general settings form, saved by the same button, with the same lack of ceremony, as changing a copyright string.
**Impact.** A misclick or a moment of inattention can take the public-facing product offline, with no distinguishing friction from any other harmless edit on the same page.
**Fix.** High-blast-radius toggles need a proportional confirmation step — reusing the same `confirm()`-class mechanism the app already applies to far lower-stakes actions (deleting a user), or, better, routing through the backend's existing Approval Engine / step-up-auth mechanisms this ERP already built for exactly this class of decision (Phase 1's `ApprovalRoutingResolver`, Identity's step-up OTP flow) rather than inventing a frontend-only confirmation.

### M5 — No settings search or discoverability at scale
**Problem.** Settings navigation is a flat, static 7-item list with no search.
**Impact.** Works today; will not work once the Configuration Platform's registration model (ADR-0018) is actually populated by every module — that architecture is explicitly designed to support potentially hundreds of declared keys across dozens of modules. A flat visual list does not scale past roughly a dozen items before a user has to scan every label to find one setting.
**Fix.** The New Admin's Command Palette (`⌘K`, already built) is the natural discoverability mechanism — settings keys should be indexable there, plus a dedicated in-workspace search filtering the Configuration Registry's own metadata (key, capability, owning module) once the Administration Workspace exists.

### M6 — Duplicated, non-componentized UI patterns
**Problem.** The exact same sticky-bottom "save bar" markup (`sticky bottom-4 z-10 ... rounded-2xl border ... shadow-soft backdrop-blur`) is hand-written twice — once inline in `UserFormPage.tsx`, once as the dedicated `SaveBar.tsx` component used by Settings. The `Row`/`Card` status-display pair in `DiagnosticsPage.tsx` is a local, page-scoped implementation of a pattern that visually recurs elsewhere. The three `feedback.tsx` components (`LoadingState`, `ErrorState`, `EmptyState`) already *are* componentized, but share an identical centered-column/icon-in-a-rounded-square layout that isn't itself factored into a shared base.
**Impact.** Not user-facing directly, but a direct source of future visual drift — the next engineer who needs a save bar or a status card will very likely hand-roll a third slightly-different version rather than find and reuse the first two, which is exactly how "one product, several inconsistent styles" architectures happen.
**Fix.** Componentize `StickyActionBar`, `StatusPanel` (unifying Loading/Error/Empty), and `KeyValueRow`/`InfoCard` (Diagnostics' `Row`/`Card`) once, in the New Admin's shared component layer, before any second consumer needs them — this is exactly the kind of "promotion, not prediction" judgment call the backend architecture already applies (Blueprint Addendum B1), now applied to the frontend.

### M7 — No differentiation of confirmation weight by risk
**Problem.** Delete-a-user, restore-a-user, reset-a-password, and verify-an-email all route through the identical `confirm()` dialog — same title/body length, same button styling, same lack of "type the name to confirm" friction regardless of reversibility or blast radius.
**Impact.** Either the low-stakes actions feel needlessly heavy (restoring a user shouldn't require the same ceremony as permanently deleting one), or — more likely, since humans habituate to repeated friction — the high-stakes actions stop feeling meaningfully different from the low-stakes ones, defeating the purpose of the confirmation entirely ("confirmation fatigue").
**Fix.** At least two tiers: a lightweight inline confirm (e.g. a two-click "Delete → Confirm" button swap) for reversible/low-risk actions, and a modal requiring explicit acknowledgment (possibly typed confirmation for the most severe cases, e.g. permanent deletion bypassing soft-delete) for irreversible/high-risk ones.

### M8 — No detail/view state distinct from edit
**Problem.** Old Admin has no read-only "view" page for its most complete CRUD resource (Users) — only list and edit.
**Impact.** A user who only has `users.view` (not `users.edit`) permission and clicks into a user record either sees an edit form with disabled fields (if that's even wired — not confirmed present) or cannot drill in at all. Neither state was directly observed as handled.
**Fix.** New Admin's DataTable/form platform should support a genuine read-only detail view as a first-class state, not a permission-disabled edit form — clearer information hierarchy, and a natural home for related-record context (audit history for this specific record, related entities) that a cramped edit form isn't built to show.

### M9 — Password change bundled into the general profile-edit megaform
**Problem.** `UserFormPage.tsx` handles create and edit with one form, including two password fields that are simply blank-means-no-change in edit mode.
**Impact.** Password/credential changes are a materially more sensitive operation than editing a bio or social links, but are visually and procedurally identical to them here — no extra verification step, no separate confirmation flow.
**Fix.** This ERP's own backend already has a purpose-built step-up authentication mechanism (Identity's OTP-based step-up flow, proven as Phase 1's own consumer). Sensitive credential changes in the new admin should route through that mechanism as a distinct, elevated flow — not a blank-to-keep text field buried in a long form.

### M10 — Inconsistent success feedback
**Problem.** Some mutations (delete, restore, cache-clear) appear to trigger an explicit success toast; the CRUD create/edit flow's only "success" signal is an implicit `navigate()` back to the list.
**Impact.** Users learn to distrust the absence of an error as confirmation of success — a save that silently redirects is indistinguishable, in the split-second after clicking Save, from a save that silently failed and redirected anyway.
**Fix.** Every mutation gets an explicit, consistent success acknowledgment (toast or equivalent) — never an implicit "no news is good news" pattern — enforced at the mutation-hook layer (a shared `useMutation` wrapper), not left to each page author's discretion.

---

## 3. Experience Improvements

Everything in this section is something AlphaSchool ERP's admin should do *better* than Old Admin, while a first-time returning user still visually recognizes it as "the same product, evolved" — not a rewrite.

1. **Real, working global search from day one** (§M1) — the Command Palette and `SearchBar` already exist in New Admin's platform layer; the discipline is to never ship a decorative substitute again.
2. **Real notifications only when real data exists** (§M2) — New Admin's `notification-center.tsx` is already query-backed; ship the UI and the data source together, always.
3. **Universal unsaved-changes protection** (§M3) — one shared hook (`useUnsavedChangesGuard` or similar) wired into every form via the platform's `forms/` layer, so no individual page author can forget it.
4. **Risk-tiered confirmations** (§M4, §M7, §M9) — a small, explicit taxonomy (reversible / destructive / high-blast-radius) driving which confirmation mechanism a given action uses, reusing the backend's existing Approval Engine and step-up-auth for the top tier rather than a frontend-only modal.
5. **Settings discoverability that scales** (§M5) — Command-Palette-indexed Configuration keys plus in-workspace search, designed against the real cardinality the Configuration Platform's architecture already anticipates (hundreds of keys), not against Old Admin's seven static categories.
6. **A componentized shared layer from the start** (§M6) — `StickyActionBar`, `StatusPanel`, `KeyValueRow`/`InfoCard` built once in `platform/components`, so the "write it twice, slightly differently" pattern never has the chance to start.
7. **A genuine read-only detail view** (§M8) as a first-class DataTable/Workspace pattern, not an afterthought.
8. **Consistent, universal success feedback** (§M10) enforced at the data-layer (a shared mutation wrapper), never left to per-page discretion.
9. **A real mobile experience, not just a non-broken one** (§1.5) — a card/list fallback for dense tables below a breakpoint, a collapsed-filters disclosure instead of a wrapping toolbar, and — critically — **the brand identity must survive on mobile auth**, where Old Admin lost it entirely.
10. **Accessibility as a built platform guarantee, not a per-component effort** (§1.6) — a skip-to-content link in `AppShell` once, `aria-live` toast announcements built into the shared toast hook once, and every interactive primitive built on audited Radix behavior rather than hand-rolled reimplementations (this is already largely true in New Admin's current component set — the discipline is to keep it true as the library grows, never reach for a hand-rolled `<button role="switch">` when `@radix-ui/react-switch` is one dependency away).
11. **RTL correctness as a protected invariant, not just a well-authored convention** — the logical-properties discipline Old Admin already does well should be enforced (a lint rule flagging physical `left/right/ml/mr` in new component code) rather than relying on every future author independently knowing the convention.
12. **A visual identity with an honest radius story** — Old Admin's own "no radius anywhere" policy is violated by nearly every real interactive component (`rounded-xl` buttons/inputs/modals, `rounded-full` badges/avatars). AlphaSchool ERP should adopt one consistent, intentional radius scale from day one (§4) rather than inherit a policy the original codebase itself never actually followed.
13. **A subtly richer motion language where it earns its keep** — Old Admin's flat `transition-colors`-everywhere approach is calm and appropriate for a dense admin UI, and should mostly be preserved; the one addition worth making is purposeful entrance/exit motion for the pieces of New Admin that Old Admin never had to solve for cleanly (modal/drawer/command-palette open+close should feel considered, not just present).

---

## 4. Design Tokens

### 4.1 Color

Old Admin's tokens (light mode), HSL, from `src/styles/globals.css`:

| Token | Old Admin (light) | Old Admin (dark) | New Admin default (light, OKLCH) |
|---|---|---|---|
| `--background` | `210 40% 99%` | `215 19% 22%` | `oklch(1 0 0)` (pure white) |
| `--foreground` | `215 28% 17%` | `210 24% 95%` | `oklch(0.145 0 0)` (near-black) |
| `--card` | `0 0% 100%` | `215 17% 27%` | `oklch(1 0 0)` |
| `--primary` | `202 44% 41%` (brand `#3B7597`) | `202 58% 60%` | `oklch(0.205 0 0)` (grayscale placeholder) |
| `--secondary` / `--muted` | `210 30% 96%` | `215 14% 33%` | `oklch(0.97 0 0)` |
| `--accent` | `202 44% 95%` | `202 34% 38%` | `oklch(0.97 0 0)` (grayscale) |
| `--destructive` | `0 72% 51%` | `0 62% 54%` | `oklch(0.577 0.245 27.325)` |
| `--border` / `--input` | `214 24% 91%` | `215 13% 38%` | `oklch(0.922 0 0)` |
| `--ring` | `202 44% 41%` | `202 58% 60%` | `oklch(0.708 0 0)` |

**Recommendation.** New Admin's `--theme inline` mapping in `admin/src/index.css` is already token-driven and already documents `--primary` as *"the organization brand-color slot ... the one token a dedicated-instance customer's branding is expected to override"* (ADR-0006). This is the correct integration point: replace New Admin's grayscale `--primary`/`--accent` OKLCH values with AlphaSchool's brand teal (`hsl(202 44% 41%)`, convertible to OKLCH), and derive `--accent` as a light tint of `--primary` (Old Admin's own `202 44% 95%` relationship) rather than a neutral gray. `--background`/`--foreground`/`--border` should shift from pure grayscale toward Old Admin's very-slightly-blue-tinted neutrals (`210 40% 99%` background, `215 28% 17%` foreground) — a subtle but real part of the "same feeling" the user asked for; pure `oklch(1 0 0)`/`oklch(0.145 0 0)` reads colder than Old Admin's actual palette.

Status colors used inline (not tokenized) in Old Admin: `emerald-500/600` (success/ok), `amber-500/600` (warning), `sky`/`violet`/`rose` (dashboard KPI accent tones). **Recommendation**: promote `success`/`warning` to real semantic tokens (`--success`, `--warning`) alongside the existing `--destructive`, rather than leaving them as ad hoc Tailwind palette classes scattered through component code — this is the one place Old Admin's own token discipline fell short and New Admin should not repeat.

**Amendment (2026-07-20): light-mode `--border`/`--input` contrast increased.** The value adopted at Phase A (`214 24% 91%`, the Old Admin light-mode value from the table above, carried through unchanged) sat only ~8-9 lightness points below `--card`/`--background` (100%/99%), subtle enough to blend into the surface on some displays — cards, inputs, tables, and section dividers all lost visible definition. Changed to `214 20% 84%` in `admin/src/index.css` — darkened and very slightly desaturated so it reads as a calm neutral line rather than a colored one as it becomes more visible, still inside the same hue family, still a token-level change (every surface using `--border`/`--input` picks it up automatically, no component override). Dark mode's `--border`/`--input` (`215 13% 38%`) is untouched — it was never the complaint. Verified live: computed-style check confirms the new value in light mode and the original byte-for-byte in dark mode, zero console errors.

### 4.2 Typography

- **Fact**: Old Admin's font stack is `Tajawal` (weights 400/500/700) for the default/Arabic path and `Inter` (400/500/600/700) for `html[lang='en']`, loaded via a single Google Fonts `@import`, with a fallback chain to `system-ui, sans-serif`.
- **Recommendation**: keep this exact pairing and weight set — it is a deliberate, correct choice for a bilingual Arabic/Latin product (Tajawal has genuinely good Arabic metrics; Inter is a strong Latin pairing at similar x-height/weight). New Admin's current `system-ui` body font (`index.css:118`) should be replaced with this pairing, self-hosted rather than Google-Fonts-CDN-loaded if this project's privacy/offline posture warrants it (not confirmed either way — flag as an open question, not a decision made here).
- **Scale**: Old Admin uses Tailwind's default type scale directly (`text-2xl font-bold` page titles, `text-sm` body, `text-xs` meta/helper text, `text-lg font-bold` modal titles) — no custom scale, no design-token-driven sizing. **Recommendation**: keep using Tailwind's default scale; introducing a custom one would be effort spent on a problem Old Admin never actually had.

### 4.3 Spacing, Radius, Elevation

- **Spacing**: consistently Tailwind's default 4px-based scale (`gap-3`, `p-4`/`p-5`/`p-6`, `space-y-5`/`space-y-6`). No custom spacing scale. Container: `mx-auto max-w-screen-2xl` with responsive padding `p-4 sm:p-6 lg:p-8`.
- **Radius — the central, explicit change request.** Old Admin's Tailwind config *forces every radius token to `0`* (`borderRadius: { none: '0', sm: '0', DEFAULT: '0', ... full: '0' }`, with an Arabic comment translating to "fixed preference: no border-radius at all on any element — complete flattening"). **This policy is not actually followed** — nearly every real interactive component overrides it locally: buttons and inputs use `rounded-xl` (0.75rem), modals/dropdowns use `rounded-2xl` (1rem), badges and avatars use `rounded-full`. The *only* genuinely radius-0 surfaces are the Sidebar/Topbar/AdminLayout chrome itself and the `AnalyticsKit`/dashboard `Panel`/`MetricCard` components, whose own source comment explicitly states *"no border-radius (system policy)"* — i.e., the flat-square treatment is real and intentional specifically for **structural chrome and data-density surfaces**, while **interactive controls were always meant to be soft**.
  **Recommendation, directly answering the user's explicit instruction:** adopt New Admin's *already-present* radius scale (`--radius: 0.625rem` ≈ 10px base, with `sm`/`md`/`lg`/`xl` derived via `calc()`) as the single source of truth — this already lands almost exactly in the requested 6–8px "subtle modern radius" range (`--radius-sm` = 6px, `--radius-md` = 8px). Apply it **uniformly**: no more silent contradiction between a "0 everywhere" config and a codebase that never actually does that. Structural/chrome/dense-data surfaces (sidebar, topbar, table container, dashboard KPI cards) may reasonably use the smaller end of the scale or none at all for a crisp, dense feel; interactive controls (buttons, inputs, modals, dropdowns, badges) use the standard scale. This preserves Old Admin's *actual* visual result (soft interactive elements, crisp structural chrome) while replacing its *self-contradicting policy* with one honest, consistent scale.
- **Elevation**: two custom shadow tokens, `soft` (`0 1px 2px 0 rgb(16 24 40 / 0.04), 0 8px 24px -6px rgb(16 24 40 / 0.08)`) and `soft-lg` (a stronger version), used for cards, modals, dropdowns, tooltips, and sticky action bars. **Recommendation**: port both tokens verbatim into New Admin's Tailwind theme — they are a genuinely well-tuned, restrained shadow system (cool neutral `rgb(16 24 40)`, low opacity, large soft blur) worth keeping exactly as-is.

### 4.4 Motion

- One custom keyframe, `fade-in` (`opacity 0→1` + `translateY(4px)→0`, `0.25s ease-out`), applied to: page content on route change, mobile sidebar drawer, `AuthLayout`'s form column, `Modal` overlay+panel, Radix dropdown/tooltip content (via `data-[state=open]:animate-fade-in`).
- Two explicit longer transitions: sidebar width (`transition-[width] duration-200`) and content padding (`transition-[padding] duration-200`) kept in lockstep during collapse/expand.
- Everything else defaults to Tailwind's implicit 150ms `transition-colors`/`transition-transform`.
- **No scale or translate hover effects anywhere** — confirmed by direct search across all reviewed components. This is a deliberate, calm, "flat" motion language appropriate for a dense admin product.
- **Recommendation**: port the `fade-in` keyframe and the two 200ms width/padding transitions verbatim. Preserve the "no hover scale/translate" discipline as an explicit rule, not an accident — it is the right choice for information density, and a design system document should say so out loud so a future contributor doesn't "improve" it into a busier, less calm product.

---

## 5. Layout System

| Surface | Old Admin | New Admin today | Recommendation |
|---|---|---|---|
| Sidebar (expanded) | Fixed `w-64`, `border-e`, flush to viewport edge, no margin | `w-56` (`SideNav`), same `border-e bg-card` shape | Converge on Old Admin's `w-64` — slightly more breathing room for bilingual labels (Arabic labels run longer than English at the same font size) |
| Sidebar (collapsed) | `w-16`, icon-only + Radix tooltip on hover | `w-14`, icon-only, no tooltip observed | Add tooltip-on-collapse (Old Admin pattern) — without it, a collapsed icon-only rail is not self-explanatory |
| Sidebar grouping | Two-level: flat items + collapsible titled groups, auto-open on active route, manual toggle overridden until route changes | **Flat, single level only** — `WorkspaceDefinition` has no grouping concept | **The one real architectural gap** — see §8 |
| Header/Topbar | `h-16`, sticky, `bg-background/80 backdrop-blur` | `h-14`, not sticky, opaque `bg-background` | Adopt Old Admin's sticky + translucent-blur treatment; height difference (16 vs 14) is minor, keep New Admin's 14 for slightly denser chrome unless testing shows otherwise |
| Content container | `mx-auto max-w-screen-2xl`, `p-4 sm:p-6 lg:p-8` | Unconstrained width, no consistent page padding convention observed | Adopt Old Admin's container + padding scale verbatim |
| Mobile nav | Full-screen overlay drawer, backdrop-blur, `animate-fade-in` | Radix `Sheet` (already equivalent primitive) | Already architecturally equivalent — apply the visual tokens (backdrop-blur, fade-in) to the existing `Sheet` usage |
| Breadcrumbs | Two-level only (Dashboard root + current section), not a true trail | None observed yet in New Admin | Old Admin's breadcrumb is honestly under-built (§Technical Debt) — New Admin should build a *real* multi-level trail (Workspace → Group → current page), since the new nested IA (§8) genuinely needs it more than Old Admin's flatter one did |
| Settings-style layout | Vertical tab nav + card-sectioned form + sticky save bar | Not yet built | Build per §3 pt. 4/6 — search-augmented nav, componentized `StickyActionBar` |
| Dashboard layout | `space-y-6` vertical stack: header → quick actions → KPI row → content grids → server status | `Dashboard` component: responsive `grid` of `WidgetDefinition`s, 1/2/4 columns | Compatible shapes — New Admin's widget grid can absorb Old Admin's visual card language (see §6) without an architecture change |
| RTL | Logical properties throughout (`ps-`/`pe-`/`border-e`/`start-`/`end-`) | Already used in the files read (`SideNav`, `TopBar`) | Continue the discipline; add a lint rule (§3 pt. 11) |
| Sticky elements | Topbar (sticky top), save bars (sticky bottom) | Topbar not currently sticky | Make Topbar sticky; save-bar-as-sticky-bottom is a pattern worth adopting wholesale (§6, `StickyActionBar`) |

---

## 6. Component Inventory

### 6.1 Old Admin's full component list (as built)

**UI primitives** (`components/ui/`): Avatar, Badge, Button, Dropdown Menu, Input, Label, Modal (hand-rolled, no Radix), Separator, Skeleton, Tooltip.
**Data** (`components/data/`): DataTable, Pagination, SearchInput.
**Form** (`components/form/`): TextField, PasswordField, SecretField, SelectField (native `<select>`), SliderField, SwitchField (hand-rolled), TextareaField, TestButton.
**Upload** (`components/upload/`): FileUploadField (image dropzone + preview), JsonUploadField (file dropzone, two-step select-then-upload).
**Analytics** (`components/analytics/AnalyticsKit.tsx`): RangeFilter, TrendChart (hand-rolled CSS bars), BarRow, DeferredNotice, MetricCard, Panel.
**Feedback** (`components/feedback.tsx`): LoadingState, ErrorState, EmptyState, PageSkeleton.
**Layout** (`layouts/`): AdminLayout, Sidebar, Topbar, Breadcrumbs, UserMenu, NotificationsButton (stub), ThemeToggle, ChatButton, ContactButton, AuthLayout, AuthCover.
**Not present at all** (confirmed absent): charting library (hand-rolled instead), date-picker library (native `<input type="date">`), combobox/autocomplete library (native `<select>`), rich text editor beyond Tiptap, drag-and-drop library (raw DOM events), any Checkbox/Radio primitive beyond raw `<input>`, any Tabs primitive beyond `NavLink`-styled-as-tabs, any Accordion beyond the sidebar's own bespoke collapsible groups, any Command Palette, any real Notification Center, any Wizard/multi-step form pattern, any Timeline component, any Drawer distinct from the mobile nav overlay.

### 6.2 New Admin's current component set (as built, pre-this-document)

`platform/components/ui/`: Avatar, Button, Dialog (Radix), Dropdown Menu (Radix), Input, Label (Radix), Select (Radix), Separator (Radix), Sheet (Radix), Table, Tooltip (Radix).
`platform/forms/`: TextField, SelectField, DateField, BilingualNameField (a New-Admin-only pattern, needed by this ERP's bilingual data model — Old Admin has no equivalent since it's not a bilingual-*data* product, only a bilingual-*UI* one), map-server-errors helper.
`platform/data-table/`: a generic TanStack-Table-backed DataTable + server-pagination hook.
`platform/modals/`: modal-host, modal-store, confirm-dialog (`useConfirm`, Dialog-based — already a real Radix `Dialog`, unlike Old Admin's hand-rolled `Modal`).
`platform/widgets/`, `platform/dashboard/`: WidgetDefinition registry + responsive grid renderer.
`platform/notifications/`: notification-center (already query-backed, ready for real data).
`platform/command-palette/`: full `cmdk`-based command palette + registry.
`platform/search/`: SearchBar + provider.
`platform/shell/`: AppShell, SideNav, TopBar, WorkspaceRoutePage, EmptyWorkspaceState, HomePage, LoginPage.
`platform/theme/`: theme-store (light/dark, already token-driven).

### 6.3 Gap analysis

New Admin already has architecturally *better* foundations than Old Admin in several places (Radix-based Dialog/Switch/Select instead of hand-rolled equivalents are available as dependencies even though Switch/Checkbox/Radio/Tabs aren't yet wired into `ui/`; a real Command Palette; a query-backed Notification Center; a bilingual name field Old Admin never needed). What New Admin is missing, purely in inventory terms, relative to what Old Admin's *visual identity* requires to feel familiar:

- Badge, Skeleton, Textarea, Switch (wire the existing Radix dependency), Checkbox, Radio, Tabs — not present in `platform/components/ui/` yet.
- SearchInput (list-scoped, debounced) — distinct from the global `SearchBar`.
- Pagination (Old Admin's numbered-with-ellipsis pattern) — DataTable currently only has prev/next.
- Feedback trio (LoadingState/ErrorState/EmptyState) as one componentized `StatusPanel` family — currently absent from `platform/`.
- PasswordField, SecretField (credential-configured indicator), SliderField.
- FileUploadField, JsonUploadField (dropzone pattern) — relevant immediately for Phase 2's Credential Vault UI (uploading a service-account JSON, e.g. Firebase's `private_key`, is a literal near-term need).
- AnalyticsKit equivalents (MetricCard, TrendChart, BarRow, Panel) for dashboard/KPI work.
- StickyActionBar (§M6), KeyValueRow/InfoCard (§M6), IconBadge (the repeated chat/contact unread-badge pattern, §Technical Debt).
- A real multi-level Breadcrumbs component (Old Admin's own is under-built, §Technical Debt — build this one properly rather than porting the weak version).

---

## 7. Page Templates

Derived from the journey analysis (§1), not from a page-by-page inventory, per the instruction to preserve experience rather than screens. Each template names the *shape* a New Admin page should follow, generalized past its one Old Admin example.

1. **List Template** — header (title + subtitle + primary "New" action) → filter/search toolbar (bordered panel) → data surface (table on desktop, card list on mobile per §3 pt. 9) → pagination. Loading = opacity-dim the existing surface during refetch, skeleton rows only on true first load.
2. **Form Template (Create/Edit)** — breadcrumb trail → title → section-carded fields (`Panel`-equivalent, icon + title + hint per section) → sticky bottom `StickyActionBar` (back + save). Unsaved-changes guard mandatory (§M3). A genuine read-only Detail Template (§M8) as a sibling, not a fallback.
3. **Settings/Configuration Template** — vertical category nav (search-augmented per §M5) + card-sectioned form per category + `StickyActionBar` save, permission-aware disable-with-note (Old Admin's `SaveBar disabled` pattern is good, keep it). Risk-tiered confirmation for high-blast-radius fields (§M4).
4. **Dashboard Template** — quick actions (permission-filtered, self-hiding) → KPI row → content/trend grids, every widget self-contained (own loading/error/permission-guard) exactly as Old Admin's `DashboardPage` already does well — this pattern generalizes cleanly onto New Admin's existing `WidgetDefinition` registry with no architecture change needed.
5. **Administrative/Diagnostic Template** — status-badge grid (`KeyValueRow`/`InfoCard`, §6.3) + manual refresh + audited destructive actions, following `DiagnosticsPage`'s shape but componentized.
6. **Audit/Activity Template** — filterable table + expandable per-row diff detail, following `ActivityLogPage`'s shape close to verbatim — it is the single best-executed page in Old Admin.
7. **Bulk-Operations Template** (Background Jobs) — select-all + per-row select + a bulk-action toolbar that only renders when the permission is held, following `FailedJobsPage`'s shape.
8. **Auth Template** — split-screen brand cover + centered form, **with a mobile-safe brand treatment** that Old Admin never built (§3 pt. 9) — e.g. a compact brand mark + solid-color header strip on narrow screens instead of full disappearance.

---

## 8. Navigation Specification

### 8.1 The architectural gap

New Admin's `WorkspaceDefinition` (`platform/navigation/workspace-definition.ts`) is intentionally flat: one workspace = one top-level nav entry = one lazily-loaded, fully self-contained component, rendered by `SideNav` as a single un-grouped list. This is correct and sufficient for most of the user's requested IA (`People`, `Identity`, `Academic`, `Students`, `Guardians`, `Employees`, `Attendance`, `Finance`, `HR`, `Infrastructure`, `Website`, `Reports`, `Developer`, `System` — all naturally flat, independent workspaces). It is **not** sufficient for the one deliberately nested item in the requested hierarchy:

```
Administration
    Configuration Platform
    Provider Registry
    Notifications
    Digital Experience
    Mobile
    Integrations
    AI Providers
    Audit
    Experience Layer
```

Each of those nine children is, correctly, its own independently-registerable capability in the backend architecture (Configuration Platform and Provider Registry already exist as real, separate Phase 1/2 deliverables; the others follow the same Registry-Pattern shape). Representing each as its own `WorkspaceDefinition` is architecturally *right* — but `SideNav` has no way to visually cluster nine flat top-level icons under one "Administration" parent the way Old Admin's `Sidebar.tsx` clusters, say, its seven `userManagement` items under one collapsible group.

**Frozen decision (naming).** The Administration child originally named `Website` in the draft is renamed **Digital Experience**, resolving the naming collision flagged in the draft's §16.1. The top-level `Website` workspace (§8.3) is unchanged in scope and ownership — it remains responsible for **CMS, Pages, Menus, Rendering, and Public Content**. `Administration > Digital Experience` is a distinct capability, owning:

- Branding
- Login Experience
- Domains
- SEO
- Analytics
- Tracking
- Social Presence
- PWA
- Public Identity

The distinction: `Website` owns *what the public sees and reads* (content); `Digital Experience` owns *how the organization presents itself across every surface* (identity, discoverability, and the login/entry experience specified in §20) — a platform-wide concern that happens to also govern the public website's branding layer, not a subset of the website's own content model. This is the same Administration/Operations boundary test already applied throughout the backend (ADR-0016 §3: Administration owns low-cardinality, low-churn reference/identity concerns; Website's own CMS content is the high-cardinality, high-churn operational data it is never Administration's job to own).

### 8.2 Recommendation — the smallest fix, not a redesign

Add one optional field to `WorkspaceDefinition`: `group?: { key: string; labelKey: string; icon: LucideIcon }`. `SideNav` renders workspaces sharing the same `group.key` under one collapsible header (Old Admin's exact interaction: click to toggle, auto-open when any child route is active, chevron rotates 180° on expand — `transition-transform`, no duration override, matching §4.4's motion inventory). Workspaces with no `group` render exactly as today, flat. This is additive to an already-frozen extension point (mirrors precisely how the backend added `ProviderSlotDefinition`'s permission fields additively in Phase 2 without reopening Phase 1 — the same discipline, applied to the frontend) — it does not change `AppShell`, routing, or any existing registered workspace's own code, and a workspace with no `group` is byte-for-byte unaffected.

### 8.3 Proposed top-level structure

```
Dashboard                                    (no group — flat, always first)
People                                       (flat)
Identity                                     (flat)
Academic                                     (flat)
Students                                     (flat)
Guardians                                    (flat)
Employees                                    (flat)
Attendance                                   (flat)
Finance                                      (flat)
HR                                           (flat)
Administration                               (group)
    Configuration Platform
    Provider Registry
    Notifications
    Digital Experience
    Mobile
    Integrations
    AI Providers
    Audit
    Experience Layer
Infrastructure                               (flat)
Website                                      (flat — CMS, Pages, Menus, Rendering, Public Content only; naming collision with Administration's former "Website" child resolved by renaming that child to Digital Experience, see 8.1)
Reports                                      (flat)
Developer                                    (flat)
System                                       (flat)
```

### 8.4 Other navigation behaviors to port from Old Admin

- **Auto-open groups on active route, single-item highlight via longest-path-match** — Old Admin's `Sidebar.tsx` resolves the *longest* matching nav path as active (so `/content/reels/analytics` highlights only itself, never its parent `/content/reels` too) — port this exact matching algorithm, it correctly prevents the "two things look active at once" bug a naive `startsWith` check would produce.
- **Manual toggle overridden by route change** — a user's manual expand/collapse of a group is a *session-scoped override*, reset the moment navigation moves outside that group. Preserves user intent without letting a stale manual collapse hide the page they're currently on.
- **Tooltip-on-hover when collapsed** — every collapsed-rail icon gets a Radix tooltip with the item's label; this is currently missing from New Admin's `SideNav` even in its flat form (§5) and should be added regardless of the grouping work.
- **Permission-gated at the item level**, not just the workspace level — Old Admin filters individual nav items by permission before deciding whether to render their parent group at all (a group with zero visible children renders nothing, not an empty header). New Admin's `useVisibleWorkspaces` already does the workspace-level equivalent (server-computed access); the same discipline should extend to sub-items within a grouped workspace's own internal navigation once built.

### 8.5 Workspace-Internal State: URL, Not Local State (binding, added 2026-07-30)

**Rule.** Any workspace-internal state that identifies a navigable resource — which record is selected, which category/tab is open, whether the page is in a list/detail/edit mode — lives in the URL (a real route, `useParams`/`useSearch`), never in local component state (`useState`) alone. Local `useState` is permitted only for state that is *not* itself a distinct, nameable place a user would reasonably expect to link to, share, refresh into, or navigate back out of (form-field drafts, a hover/focus flag, an open/closed dropdown).

**Why this is a rule, not a one-off Academic decision.** Surfaced during UI Sprint 1-A's implementation (§28.16): `router.tsx` originally exposed exactly one route per workspace (`/w/$workspaceKey`), and both existing implemented workspaces — Configuration Platform (§26.3) and Provider Registry (§27.3) — navigate their own List↔Detail step (category rail → settings form; Overview Grid → provider form) via local `useState`, never the URL. This was never a deliberate design decision recorded anywhere — it was simply what shipped before any workspace's own internal state needed to survive a refresh or be shared. It also directly contradicts §7.1's own original, already-frozen praise for Old Admin's List Template ("page state lives in the URL — good, deep-linkable, refresh-safe"), which neither of the two shipped workspaces actually implements. Left undocumented, a third and fourth workspace would very plausibly repeat the local-state shortcut simply because it's what the two existing precedents show — exactly the "two navigation patterns coexisting with no one deciding between them" outcome this section exists to prevent.

**What changed to support this.** `router.tsx`'s single `/w/$workspaceKey` route is now a parent with two children — an index route (`/`) and a splat route (`$`) capturing everything past the workspace root — registered once, generically, for every workspace (ADR-0015 Decision 4's "this file never changes when a workspace is added" invariant, unchanged). A new `useWorkspaceSubPath()` context (`platform/navigation/workspace-sub-path.ts`) threads the splat value down; a workspace that never calls it is entirely unaffected. §28.3/§28.15 (Entity List/Detail/Form, the Flat Tab Switcher) are this rule's first real consumer.

**Status of the two existing exceptions.** Configuration Platform and Provider Registry are **not** retroactively migrated by this rule — that would be an unscoped rewrite of already-shipped, frozen work, not this section's job. Both are recorded here as a **known, disclosed gap against this rule** (added to `docs/IMPLEMENTATION_PLAYBOOK.md`'s own live Technical Debt Register — not this document's §17, which is a frozen, point-in-time analysis of Old Admin specifically, not a running New Admin debt list) rather than silently grandfathered in or forgotten: their own two-level List↔Detail step is not deep-linkable or refresh-safe today, and migrating them to the same index+splat shape §28 now uses is a legitimate, real future task — just not one bundled into UI Sprint 1-A's own scope.

---

## 9. UX Specification

Consolidates the interaction rules a component library must obey, derived from the journeys and the improvements list. This is the section a future implementer should check a new screen against.

- **Every mutation gets exactly one, explicit, consistent success acknowledgment.** Never an implicit "silent redirect = success" (§M10).
- **Every form with any field checks `isDirty` before allowing navigation away, no exceptions.** (§M3)
- **No visual affordance ships before its mechanism does.** (§M1, §M2) — an empty/disabled/hidden control is always preferable to a fake one.
- **Confirmation weight is proportional to risk**, using a shared, named risk taxonomy (reversible / destructive / high-blast-radius), never a single one-size-fits-all dialog. (§M4, §M7, §M9)
- **List pages never blank-and-reflow during a background refetch** — dim the existing content (`opacity-70`-class treatment), reserve skeleton rows for true first-load only. (Old Admin already does this correctly — preserve it.)
- **Every icon-only interactive element has an `aria-label`, no exceptions** — audited at component-library level via a lint rule, not per-author discipline. (§1.6)
- **Every page/component new to the New Admin platform layer is built on an audited Radix primitive where one exists** — never hand-roll a Switch, Dialog, or Dropdown when the dependency is one `npm i` away and already partially in use. (§1.6)
- **Logical CSS properties only** (`ps-`/`pe-`/`start-`/`end-`/`border-e`/`border-s`) — a lint rule should flag `ml-`/`mr-`/`left-`/`right-` in new component code, converting Old Admin's well-executed *convention* into an enforced *invariant*. (§1.7)
- **`dir="ltr"` is applied surgically to genuinely-LTR content spans** (dates, emails, phone numbers, technical IDs, stack traces, URLs) inside RTL layouts — never to a whole page/section as a blunt instrument. (§1.7)
- **Radius is applied per the single unified scale (§4.3)** — chrome/dense-data surfaces may use the low end or none; every interactive control uses the standard scale. No component silently overrides radius to fight the system default the way Old Admin's own components fought its own config.
- **Motion stays calm** — `transition-colors`/`transition-transform` for interactive-state changes, the one `fade-in` keyframe for entrance, no hover scale/translate effects, matching Old Admin's restrained language exactly. (§4.4, §3 pt. 13)

---

## 10. Component Mapping — Old Component → New Component

| Old Component | New Component | Notes |
|---|---|---|
| `components/ui/Button` | `platform/components/ui/button.tsx` | Already exists; port `soft` shadow on default/destructive variants, keep `rounded-*` per §4.3's unified radius scale |
| `components/ui/Badge` | *(to build)* `platform/components/ui/badge.tsx` | Port variant set (default/success/muted/destructive) + `rounded-full` |
| `components/ui/Modal` (hand-rolled) | `platform/modals/*` (Radix `Dialog`-based) | **Upgrade, not port** — New Admin's Dialog is already Radix-based; apply Old Admin's visual tokens (soft-lg shadow, fade-in, `rounded-2xl`, 3-size scale) to it, discard the hand-rolled focus-trap/ESC logic entirely |
| `components/ui/Input` | `platform/components/ui/input.tsx` | Already exists; port `h-11 rounded-xl` sizing + `aria-[invalid=true]` variant styling |
| `components/ui/Avatar` | `platform/components/ui/avatar.tsx` | Already exists; port fixed-size + `bg-primary/10 text-primary` fallback treatment |
| `components/ui/DropdownMenu` | `platform/components/ui/dropdown-menu.tsx` | Already exists (Radix); port `rounded-2xl shadow-soft-lg` content styling |
| `components/ui/Tooltip` | `platform/components/ui/tooltip.tsx` | Already exists (Radix); port inverted-color-scheme styling |
| `components/ui/Separator`, `Skeleton` | `platform/components/ui/separator.tsx`; *(to build)* `skeleton.tsx` | Separator exists; Skeleton needs building, trivial (`animate-pulse rounded-* bg-muted`) |
| `components/data/DataTable` | `platform/data-table/data-table.tsx` | **Upgrade, not port** — New Admin's is TanStack-Table-backed (real sorting/column config) vs. Old Admin's static-header table; port the visual language (rounded-2xl border container, muted header row, hover row tint, RTL-safe `align` prop) onto the existing, more capable engine |
| `components/data/Pagination` | *(to build)* `platform/data-table/pagination.tsx` | Port numbered-with-ellipsis logic, fix the dual-icon RTL swap into a single `rtl:rotate-180` (§1.7 inconsistency) |
| `components/data/SearchInput` | *(to build)* `platform/data-table/search-input.tsx` | Port debounce + icon-inset pattern, distinct from the global `SearchBar` |
| `components/form/TextField` | `platform/forms/text-field.tsx` | Already exists; port error-below-field + no-asterisk convention (see §11 re: required-field indication, a genuine improvement opportunity) |
| `components/form/SelectField` | `platform/forms/select-field.tsx` | Already exists as Radix-based (an upgrade over Old Admin's native `<select>`); port visual tokens only |
| `components/form/PasswordField`, `SecretField` | *(to build)* | Port show/hide toggle + `SecretField`'s "configured" badge — directly relevant to Phase 2's Credential Vault forms |
| `components/form/SwitchField` (hand-rolled) | *(to build, on Radix Switch)* | **Upgrade, not port** — rebuild on `@radix-ui/react-switch` (already a resolvable dependency per New Admin's package set), port the bordered-row visual layout only |
| `components/form/SliderField`, `TextareaField`, `TestButton` | *(to build)* | Straightforward ports |
| `components/upload/FileUploadField`, `JsonUploadField` | *(to build)* | Direct port of dropzone pattern; near-term need for Credential Vault file-based secrets (e.g. Firebase service-account JSON) |
| `components/analytics/AnalyticsKit` (MetricCard, TrendChart, BarRow, Panel, RangeFilter, DeferredNotice) | *(to build)* `platform/widgets/*` additions | Port visual language onto the existing `WidgetDefinition` registry; `DeferredNotice`'s "don't show fake numbers" honesty pattern is worth explicitly preserving |
| `components/feedback` (LoadingState/ErrorState/EmptyState/PageSkeleton) | *(to build)* `platform/components/status-panel.tsx` | Componentize as one shared-base family (§M6) rather than three parallel implementations |
| `layouts/AdminLayout`, `Sidebar`, `Topbar` | `platform/shell/app-shell.tsx`, `side-nav.tsx`, `top-bar.tsx` | Already exist; apply visual tokens + the grouping extension (§8) |
| `layouts/components/Breadcrumbs` | *(to build, properly this time)* | Do not port Old Admin's 2-level-only implementation verbatim — build a genuine multi-level trail, since the new grouped IA needs it more |
| `layouts/components/UserMenu` | Extend `top-bar.tsx`'s existing dropdown | Already architecturally present; port visual tokens |
| `layouts/components/NotificationsButton` | `platform/notifications/notification-center.tsx` | **Already ahead of Old Admin** — already query-backed; do not port the stub, only the bell+badge visual treatment |
| `layouts/components/ThemeToggle` | `platform/theme/*` + a `top-bar.tsx` addition | Theme store already exists; port the tooltip-wrapped dual-icon-button visual pattern |
| `layouts/components/ChatButton`, `ContactButton` | *(to build)* `platform/components/icon-badge-button.tsx` | Componentize the repeated unread-badge pattern once (§M6), consume it for both, and for any future icon-badge need |
| `layouts/AuthLayout`, `AuthCover` | `platform/shell/login-page.tsx` + a new cover component | Port split-screen shape; **fix the mobile-disappears-entirely gap** (§M/§3 pt. 9) as part of the port, not after |
| `hooks/useToast` (SweetAlert2-based) | *(to build)* a native toast system | **Do not port SweetAlert2.** It is a heavyweight, visually-foreign (non-Tailwind-token-driven, hardcoded hex colors) dependency for what should be a lightweight, theme-native toast. Build on a Radix-compatible toast primitive so it inherits the design-token system automatically instead of hardcoding light/dark hex pairs. Port only the *behavior* (position swap by direction, 3.2s timer, `confirm()` promise-based API shape) |
| `router/ProtectedRoute`, `NewspaperEnabledRoute` | TanStack Router's own guard mechanisms + New Admin's existing server-computed workspace-access model | Old Admin's permission model is client-declared (`permission?: string`); New Admin's is already server-authoritative (`useVisibleWorkspaces` intersects server response) — do not port the client-declarative pattern, it is architecturally weaker than what New Admin already has |

---

## 11. Components That Should Be Completely Redesigned

- **`Modal`** — hand-rolled, no Radix, unaudited focus-trap/ESC behavior. New Admin's Dialog is already Radix-based; this is a full replacement, not a visual port (§10).
- **`SwitchField`** — hand-rolled `<button role="switch">` with manual RTL thumb-translate math. Rebuild on `@radix-ui/react-switch`, which handles this correctly and for free.
- **`useToast`/SweetAlert2** — foreign visual language (hardcoded hex, not token-driven), heavyweight dependency for toast+confirm. Rebuild as a native, token-driven toast system; keep `confirm()`'s promise-based ergonomics but implement it on the Dialog primitive New Admin already has, not a second, unrelated modal system.
- **Breadcrumbs** — under-built (2-level-only in Old Admin); the new grouped IA (§8) needs a genuine multi-level trail, so this is a from-scratch build informed by, not copied from, Old Admin.
- **Settings navigation** — Old Admin's flat static list does not survive the Configuration Platform's real cardinality (§M5); needs search/discoverability designed in from the start, not retrofitted.
- **Required-field indication** — Old Admin has literally none (confirmed: no asterisk, no "(required)" text anywhere in any form field component). This should be designed properly in the new `TextField`/etc. base, not carried forward as an absence.

## 12. Components That Should Remain Almost Identical (Visual Language Only)

- **Button, Input, Avatar, DropdownMenu, Tooltip, Separator** — sound token-driven implementations already; port the visual tokens (radius, shadow, spacing) onto New Admin's existing, already-correct Radix-based equivalents.
- **DataTable's visual shell** (rounded-2xl bordered container, muted header row, hover tint, RTL-safe alignment) — the *engine* upgrades (TanStack Table), the *skin* ports directly.
- **AnalyticsKit's visual language** (square/bordered "data-density" surfaces distinct from soft-rounded "interactive" surfaces) — a genuinely good, deliberate distinction worth preserving exactly.
- **The `fade-in` keyframe and the calm, no-hover-scale motion language overall** (§4.4) — this is core to "the same feeling" the user asked for and should not be touched.
- **The sidebar's auto-open/longest-path-match/session-scoped-override interaction logic** (§8.4) — genuinely well-designed, port the algorithm as-is onto the new grouped data model.
- **The dashboard's "every widget self-contained, silently self-hides if unauthorized" philosophy** — already compatible with New Admin's `WidgetDefinition` registry with zero changes needed.

## 13. Components That Should Be Removed

- **SweetAlert2** as a dependency entirely (superseded per §11 — its visual language cannot be made token-driven without defeating the point of removing it).
- **The decorative Topbar search button** (§M1) — remove outright, replaced by the already-real `SearchBar`/Command Palette, never re-shipped as a placeholder.
- **The `NotificationsButton` stub's hardcoded-empty pattern** (§M2) — remove the fake affordance; New Admin's real, query-backed `NotificationCenter` replaces it directly, no interim stub needed since one already doesn't exist in New Admin.
- **Native `<select>`-based `SelectField`** — superseded by New Admin's already-built Radix-`Select`-based field; do not reintroduce the native-select pattern.
- **Client-declared route permission strings** (`ProtectedRoute permission="..."`) as the primary access-control mechanism — New Admin's server-computed `useVisibleWorkspaces` model is strictly better (§10) and should be the only pattern, not a parallel second one.

## 14. Components to Add for an Enterprise ERP (Not Present in Old Admin at All)

- **A real Detail/View template** distinct from Edit (§M8) — Old Admin never needed this because it never had a permission model this granular in practice; AlphaSchool's actual permission model (view vs. edit as genuinely separate grants, already core to the backend's `required_permission_to_view`/`required_permission_to_edit` pattern from ADR-0018) requires it.
- **A Wizard/multi-step form pattern** — nothing in Old Admin needed one; onboarding flows, guided setup (e.g. a first-time Provider credential setup walking through Vault write + health check), and complex multi-entity creation in an ERP context will.
- **A Timeline component** — Old Admin's ActivityLogPage gets close (expandable diff rows) but is table-shaped, not timeline-shaped; entity-level "everything that happened to this record" views (a natural Administration Experience Layer consumer, ADR-0021) want a real timeline.
- **A generic Health/Status badge + panel system**, promoted from `DiagnosticsPage`'s one-off `Row`/`Card`/`okBadge` helpers into `platform/components` — directly needed by Phase 2's `HealthCheckRunner` output (provider health results) and any future Configuration Health Engine (ADR-0021 Decision 6).
- **A Credential/Secret field family** that understands the Vault's specific shape (configured/not-configured state, "leave blank to keep," sensitivity marking per §Phase 2's own recorded future-consideration on credential sensitivity classification) — a superset of Old Admin's `SecretField`, purpose-built for the Provider Registry.
- **An approval-request UI pattern** — nothing in Old Admin needed one (it has no Approval Engine); AlphaSchool's backend has had one since Sprint 1.2 and it is now a live mechanism in both the Configuration Platform and Credential Vault write paths. A pending-approval banner/badge and an approve/reject action pattern are needed and have zero Old Admin precedent to draw from.
- **A risk-tiered confirmation system** (§M4, §M7, §3 pt. 4) as a first-class, named platform primitive — not an ad hoc per-page `confirm()` call.
- **A Command-Palette-indexed settings/configuration search** (§M5, §3 pt. 5) — no precedent in Old Admin.

---

## 15. Migration Strategy

This is a **rebuild of visual language on new bones**, not a migration of code. Sequencing, respecting the already-frozen extension-point discipline (ADR-0015 Decision 4 — no workspace addition may require editing `platform/`):

1. **Design tokens first** (§4) — update `admin/src/index.css`'s `@theme`/`:root`/`[data-theme='dark']` blocks with the brand palette, radius scale confirmation, shadow tokens, and font stack. Zero component code changes; every existing shell/shadcn-default component immediately re-skins for free, since they're already token-driven. This is the single highest-leverage, lowest-risk step, and should be done and visually verified in isolation before anything else.
2. **Shared component layer** (§6.3, §10 "to build" rows) — Badge, Skeleton, Textarea, Switch (Radix-based), PasswordField/SecretField, StickyActionBar, StatusPanel, KeyValueRow/InfoCard, IconBadgeButton, Pagination, SearchInput (list-scoped). Each is a small, independently-testable unit; build and visually verify against the token system from step 1.
3. **Navigation grouping extension** (§8.2) — the one genuine `WorkspaceDefinition` schema addition, additive and backward-compatible, unblocking the Administration group's IA before any real workspace is registered.
4. **Platform-level UX guarantees** (§9) — the unsaved-changes guard hook, the risk-tiered confirmation primitive, the universal success-toast mutation wrapper, the `aria-label`/logical-property lint rules. These belong in the platform layer precisely so no individual future workspace author can opt out by omission (§M3, §M6, §M7, §M10's root cause was always "left to per-page discretion").
5. **Page templates** (§7) as reusable layout components/hooks in `platform/`, proven against one real, low-stakes workspace first — the Administration workspace's own Configuration Platform / Provider Registry screens are the natural first real consumer, since their backend (Phases 0–2) is already frozen and live, and building their UI is explicitly named as deferred-not-abandoned work from the Phase 2 sign-off.
6. **Auth/first-impression polish** (§1.1, §3 pt. 9) — the mobile-safe brand cover fix, loading-shell refinement. Lower urgency than 1–5 since it affects a smaller fraction of total interaction time, but should not be deferred indefinitely given it's every new user's literal first impression.
7. **Everything else** — remaining workspaces (People, Identity, Academic, ...) are built per-workspace, each a self-contained consumer of the now-complete platform layer, in whatever order the backend Phase sequence and business priority dictate. No further `platform/` changes should be required for a normal workspace addition, per ADR-0015's own governing constraint.

Each step above should get its own real negative-case proof where one applies (e.g., the unsaved-changes guard should be proven by actually triggering a route change mid-edit and confirming the block fires, not merely code-reviewed) — the same discipline already standing for every backend phase in this project.

---

## 16. Risks

1. **RESOLVED — naming collision**: the draft flagged identical `Website` labels at two IA levels. Resolved per the frozen decision in §8.1: the Administration child is renamed **Digital Experience** (Branding, Login Experience, Domains, SEO, Analytics, Tracking, Social Presence, PWA, Public Identity); the top-level `Website` workspace keeps its scope unchanged (CMS, Pages, Menus, Rendering, Public Content).
2. **Token migration regressions**: because New Admin's current components are *already* fully token-driven (a real strength), a tokens-only pass (§15 step 1) carries low but non-zero risk of an un-anticipated visual break wherever a component hardcodes a color/radius instead of using the token (worth a quick audit pass before declaring step 1 complete).
3. **SweetAlert2 removal is a real behavior change, not just a re-skin** — its `confirm()` is used in several places already (§10); removing it requires re-wiring every call site, not just restyling. This should be scoped and sequenced deliberately, not treated as equivalent effort to a pure CSS token swap.
4. **The grouping extension (§8.2) is the one place this document proposes touching an already-frozen extension point** (`WorkspaceDefinition`). It is designed to be additive and non-breaking, matching the same discipline already used twice in the backend (Phase 1/2's own additive fixes to frozen scaffolds) — but it should go through the same "smallest possible documented decision" review this project applies to every such change, not be treated as pre-approved by this document alone.
5. **Font licensing/hosting**: Tajawal + Inter via Google Fonts CDN (Old Admin's current method) is a network dependency and a minor privacy/offline consideration for an ERP that may run in restricted environments — flagged as an open question in §4.2, not resolved here.
6. **Scope creep risk on §14's "Enterprise ERP additions"** — several of these (Wizard, Timeline, Approval UI) are genuinely substantial components, not small ports. They should be built against real, specific consumers (the way `BilingualNameField` was clearly built against this ERP's actual bilingual data model) rather than speculatively, matching the whole project's B1 promotion-not-prediction discipline.

## 17. Technical Debt Inherited From Old Admin

(Findings that should inform what *not* to copy, distinct from UX mistakes users would notice — these are code-quality/maintainability observations.)

1. **The "0 radius everywhere" Tailwind config is dead policy** — actively contradicted by the majority of real components (§4.3). A design system document should never let a config lie about what the product actually looks like; the new tokens file must be honest about the real, intended radius scale from day one.
2. **Duplicated sticky-save-bar markup** (§M6) — copy-pasted, not componentized, between `UserFormPage.tsx` and `SaveBar.tsx`.
3. **Duplicated unread-badge markup** between `ChatButton.tsx` and `ContactButton.tsx` — identical class strings, not extracted.
4. **`DiagnosticsPage`'s `Row`/`Card` helpers are page-scoped**, not shared, despite the same visual shape recurring across multiple administrative pages.
5. **SweetAlert2's hardcoded hex colors** (`#162130`/`#e5edf5` dark, `#ffffff`/`#1f2a37` light) mean the toast system does not actually track the HSL token system the rest of the app is built on — a real, if minor, design-system leak.
6. **No test coverage evidenced** for any UI component (no `*.test.tsx` files found alongside any reviewed component, no Storybook) — New Admin already has Vitest + Testing Library wired (`vitest.config.ts`, `@testing-library/react` in `devDependencies`) and should hold every new shared component to that bar from the start, unlike Old Admin.
7. **Client-declared permission strings at the route level** (`ProtectedRoute permission="..."`) sit alongside a *separate*, more authoritative server-computed check pattern used elsewhere (nav-level `hasPermission`) — two parallel permission-declaration mechanisms in one codebase is itself a maintainability smell, resolved architecturally in New Admin already (§10's last row) but worth naming so it's never reintroduced.

## 18. Final Recommendations Before Implementation

1. **Do tokens first, alone, and verify visually before anything else** (§15 step 1) — the highest-leverage, lowest-risk, most immediately-gratifying step, and the one most likely to produce the "I recognize this" feeling the user asked for on day one.
2. ~~Resolve the `Website`/`Administration > Website` naming collision as an explicit product decision before the navigation spec (§8) is implemented.~~ **RESOLVED** — see §8.1 (Administration's child renamed Digital Experience).
3. **Treat §9 (UX Specification) as the actual acceptance checklist** for every new component and page going forward — it is the concrete, checkable form of "familiar in the first five minutes, better after five minutes."
4. **Do not port SweetAlert2, the hand-rolled Modal, or the hand-rolled Switch** under any circumstance, even temporarily "to move faster" — New Admin already has strictly better primitives available (Radix Dialog/Switch) and reintroducing the old ones would be a genuine regression, not a neutral shortcut.
5. **Build the Administration workspace's own screens (Configuration Platform, Provider Registry) as the first real proof of every pattern in this document** — its backend is already frozen and live (Phases 0–2), its UI was explicitly deferred rather than abandoned at Phase 2 sign-off, and it is the workspace best positioned to validate the grouping extension (§8.2), the Credential/Secret field family (§14), and the risk-tiered confirmation system (§14) all at once, against real, already-working APIs rather than mocked ones.
6. ~~This document is not an ADR and does not freeze anything on its own — it awaits explicit review and approval before any of §15's implementation steps begin.~~ **Superseded by §22 — the document is now frozen.**

---

## 19. Iconography System (Frozen Decision)

### 19.1 Evaluation

Three candidates evaluated against the ten criteria requested, all as React packages (`lucide-react`, `@tabler/icons-react`, `@heroicons/react`):

| Criterion | Lucide | Tabler | Heroicons |
|---|---|---|---|
| **Enterprise ERP usage** | Strong — de facto standard in the shadcn/Radix ecosystem this stack is already built on; widely proven at dashboard/admin scale | Strong — Tabler itself began as an admin-dashboard kit, so ERP-adjacent icon coverage (devices, business, finance, transport) is deep | Weak at ERP scale — designed as a focused UI-chrome set for marketing/product sites, not a full application icon vocabulary |
| **Long-term consistency** | High — a smaller, tightly curated ~1,500-icon set with a single, disciplined 24×24/2px-stroke/round-cap grid enforced across the whole library | Medium-high — a much larger ~5,700-icon set built by many contributors over a longer history; overall consistent, but minor stroke/corner-radius drift is more likely to creep in across such a large surface | High — a very small, hand-polished ~300-icon set; consistency is easy to maintain precisely because the set stays small |
| **Outline quality** | Excellent — clean geometric outline style, deliberately restrained | Excellent — comparable outline quality, slightly more literal/detailed in some icons | Excellent, arguably the most refined per-icon, but on too small a set to matter at ERP scale |
| **RTL appearance** | Neutral — no RTL-specific variants in any of the three; all rely on the consuming app's own `rtl:rotate-180`/logical-property handling for directional icons (arrows, chevrons). Old Admin already solved this correctly *for Lucide specifically* — continuing with Lucide means zero re-verification of already-proven RTL behavior | Neutral, same caveat, but every directional icon's RTL behavior would need re-verifying from a cold start | Neutral, same caveat, and the smallest directional-icon set to re-verify |
| **Dashboard readability** | Strong at 20–24px — icons stay legible and geometrically clean at typical KPI/widget sizes | Strong, comparable | Strong, comparable, but limited coverage forces mixing icon styles once the dashboard needs a concept Heroicons doesn't have |
| **Dense data tables** | Strong at 16px — stroke weight holds up without looking like a smudge at the smallest common UI size | Strong, comparable | Comparable at 16px, same coverage caveat |
| **Navigation** | Strong — very complete coverage of generic nav/module concepts (already proven across Old Admin's entire 13-section nav tree, which is 100% Lucide today) | Strong, comparable coverage, but zero continuity with what Old Admin's users already recognize | Insufficient — would run out of icons for a nav tree this large (Old Admin's nav alone uses ~50 distinct icons; Heroicons' full outline set is ~300, and ERP-specific concepts like "gradebook" or "bus route" are not guaranteed to exist) |
| **Forms** | Strong — full coverage of form-adjacent icons (eye/eye-off, calendar, upload, link, checkmark states) | Strong, comparable | Strong for the common cases, thin for anything specific |
| **Settings/Administration** | Strong — sliders, plug, cloud, shield, key, server, database, webhook-shaped icons all present and already in active use in Old Admin's own Administration-adjacent nav | Strong, comparable, larger raw count | Thin — Heroicons' scope was never meant to cover a Provider Registry / Credential Vault / diagnostics-panel vocabulary this specific |
| **Accessibility** | Equal — icon libraries do not themselves provide accessibility; each ships plain SVG with no baked-in `aria-hidden`/`role`, meaning the same discipline (§9: every icon-only control gets an explicit `aria-label` on its interactive parent) applies identically regardless of which library is chosen | Equal | Equal |
| **Future scalability** | Very strong — ~1,500 icons today, actively and frequently released, more than sufficient for every domain this ERP's own Blueprint enumerates (Academic, Admissions, Finance, HR, Inventory, Library, Transportation, LMS, Reporting, Identity, Media, Notifications, and all nine Administration capabilities) with real headroom | Strongest in raw count (~5,700) but that headroom is never actually needed at the scale a navigation/action icon vocabulary reaches in practice (typically low hundreds of distinct icons even in a large ERP) | Weakest — ~300 icons will not comfortably cover this product's full domain vocabulary without a second library, which directly violates the "one library, no exceptions" requirement |

### 19.2 Decision

**Lucide Icons (`lucide-react`) is the official AlphaSchool Design System icon library. No other icon library, and no raw/custom SVGs outside this set, may be used anywhere in the ERP frontend.**

Reasoning, beyond the table above:

1. **Lucide is already the icon library in both codebases.** `lucide-react` is a dependency of Old Admin (`^0.454.0`) *and* New Admin (`^1.24.0`) today. Every icon shape a returning user already recognizes — the exact `Settings` gear, the exact `Users` glyph, the exact `ShieldCheck` — is already Lucide. Switching to Tabler or Heroicons would mean the *style* might feel similar but literally none of the specific icon shapes would be the ones users already know, directly undermining the whole project's "same feeling" mandate. This is the single most decisive factor and is not present in the same way for either alternative.
2. **Consistency at the scale this product actually needs beats raw count.** Tabler's larger set is a real asset for icon-picker-style features with thousands of choices; it is not an asset for a navigation/action vocabulary that, even across this ERP's full 15+ domain modules, will land in the low-to-mid hundreds of distinct icons — comfortably inside Lucide's coverage, with less stylistic variance risk than a 5,700-icon multi-contributor set.
3. **Ecosystem fit.** New Admin's stack (Radix primitives, `cva`, shadcn-shaped conventions) treats Lucide as its default icon assumption; any future shadcn-ecosystem recipe or component adopted wholesale will already assume Lucide-shaped icon usage.
4. **Outline-by-default matches the "no filled icons" policy precisely.** Lucide's core set is outline-only; filled variants exist only as explicitly separate, individually-opted-into icons (e.g. a small number of `*-Filled`-style exceptions), which is the correct shape for a "filled is the rare, deliberate exception" policy. Tabler ships a full parallel filled set for most icons by default, a larger standing temptation for filled-icon creep across a large team over time.

### 19.3 Sizing Scale (Frozen)

All sizes as the icon's own bounding box (Tailwind `size-*`/`h-* w-*`), independent of any surrounding hit-target padding:

| Context | Size | Tailwind | Notes |
|---|---|---|---|
| **Table / dense data** | 16px | `size-4` | Inline table-cell icons, compact row actions — the smallest size in the system, never go smaller |
| **Button (default)** | 16px | `size-4` | Matches New Admin's already-established `Button` cva convention (`[&_svg]:size-4`) — no change needed |
| **Button (large/primary CTA)** | 20px | `size-5` | Reserved for `size="lg"` buttons only |
| **Form fields** (inline field icons — password toggle, search icon inset, upload icon) | 16px | `size-4` | Matches button/table density |
| **Toolbar / Topbar action icons** (search, theme toggle, notifications, user-menu chevron) | 20px | `size-5` | Rendered inside a 36–40px hit target (`h-9`/`h-10` icon button), per accessible touch-target guidance — the icon is smaller than its clickable area, never the reverse |
| **Sidebar navigation** (both collapsed rail and expanded label state) | 20px | `size-5` | One size regardless of collapsed state, per Old Admin's own precedent (`Sidebar.tsx`'s consistent `h-5 w-5`) — the icon must not visually resize when the label appears/disappears |
| **Dashboard / KPI / widget icons** | 20px | `size-5` | Rendered inside a 40px colored badge square (`h-10 w-10`), matching Old Admin's `MetricCard`/section-`Panel` icon-badge pattern exactly |
| **Status panels** (Loading/Error/Empty state) | 24px | `size-6` | Rendered inside a larger 56px badge (`h-14 w-14`), the one place a bigger icon is warranted since it is the primary content of an otherwise-empty view |

### 19.4 Stroke Width (Frozen)

**2px stroke width, uniformly, at every size in §19.3 — Lucide's own default, unmodified.** No per-size stroke tuning. A thinner stroke (e.g. 1.5) at 16px reads as weak/blurry at that size, and stroke-width tuning by context is a maintenance burden with no real visual payoff for an interface at this density. The one narrow, named exception: a purely decorative/illustrative icon larger than 32px (if one is ever introduced, e.g. an auth-page illustration accent) may use 1.5 for a lighter, less clinical feel — this is an explicit, rare exception, never a second general rule.

### 19.5 States (Frozen)

| State | Treatment |
|---|---|
| **Default (inactive)** | `text-muted-foreground` — matches Old Admin's inactive-nav-icon convention exactly |
| **Hover** | `text-foreground` (or `text-accent-foreground` inside an already-`hover:bg-accent` interactive surface), via `transition-colors` — never a scale/translate hover effect, per §4.4's calm-motion rule |
| **Active / selected** (current nav item, toggled-on state) | `text-primary` |
| **Disabled** | Inherits the parent control's `disabled:opacity-50` (already the standing convention on `Button`'s own cva) — never a separate icon-level disabled treatment, and never a different disabled color, only reduced opacity |
| **Focus** | Icons never receive their own focus ring — the parent interactive element's existing `focus-visible:ring-2 focus-visible:ring-ring` treatment (§9) is sufficient and correct; adding a second, icon-level focus style would be a redundant, inconsistent-looking double ring |

### 19.6 No filled icons — the policy, precisely

Outline icons only, everywhere, with exactly one narrow exception class: a small unread/status **dot** or **badge** (already an established pattern — Old Admin's chat/contact unread badge, §10's `IconBadgeButton`) is not an "icon" in this policy's sense and may remain a solid-filled shape, since it is functioning as a notification indicator, not as a glyph representing a concept. Any future request to use a filled *icon* (as opposed to a filled *indicator dot*) requires an explicit, named, strong reason recorded at the point of use — the default, unstated assumption is always outline.

---

## 20. Login Experience (Frozen Decision)

The login page is a first-class Design System surface, not an authentication afterthought — it is the single highest-leverage moment for the "same feeling, better experience" mandate (§0), since it is the one screen every user, on every device, sees before anything else.

### 20.1 Structure

Retains Old Admin's split-screen shape (§7 Auth Template, §10) as the base composition — a brand column and a form column — but fixes its one confirmed failure (§1.1, §3 pt. 9: the brand column fully disappearing below `lg`) and extends it into a genuinely configurable, premium entry experience:

- **Desktop/tablet (`≥lg`)**: two-column split, brand column first (start side, respecting RTL), form column second, matching Old Admin's proportions.
- **Mobile (`<lg`)**: the brand column does **not** disappear. It collapses into a compact header band — solid brand-color strip (or a cropped slice of the configured background image, never a full illustration crush) containing the school logo and, space permitting, the rotating welcome title — above a full-width centered form. This is the concrete fix for the confirmed mobile identity loss.
- The brand column's content is layered, in this priority order, so lower layers gracefully degrade if a higher one isn't configured: **background layer** (image, slider, or video — §20.3) → **overlay** (Old Admin's radial-gradient scrim, kept, for text legibility over any background) → **content** (logo, welcome title/description, rotating motivational message, version/copyright footer).

### 20.2 School logo — sourced from the Configuration Platform

The logo is never a hardcoded asset. It resolves through the already-built Configuration Platform (`SettingsResolver`, ADR-0018) exactly as Identity's OTP settings already do (Phase 1's own proof consumer) — a `DeclaresSettingsSchema` entry (owning module: the new Digital Experience capability, §8.1) declaring a Configuration key (e.g. `digital-experience.branding.logo_media_id`, `translatable_category` not applicable — a media reference, not text) whose resolved value is a Media reference (the already-frozen Media Architecture, `docs/DOMAIN_BLUEPRINT.md` §12) rather than a raw URL. This gives the login page automatic light/dark logo variants for free if the Configuration schema declares two keys (`logo_light_media_id` / `logo_dark_media_id`) rather than one, exactly the kind of small, explicit metadata decision this Configuration model already supports.

A logo that fails to resolve (not yet configured, e.g. immediately after installation — see §21.2) falls back to the AlphaSchool product wordmark, never a broken image or empty space.

### 20.3 Background — image, slider, or video (mutually exclusive, Configuration-driven)

Three modes, selected by a single Configuration key (`digital-experience.branding.login_background_mode`: `image` | `slider` | `video` | `none`), each with its own Configuration-backed asset reference(s) (again, Media references, not raw URLs):

- **`image`** — one static background image, object-fit cover, behind the existing gradient overlay.
- **`slider`** — an ordered set of images (a `requires`-declared array of Media references), auto-advancing on a fixed interval (recommend 8s, matching a "calm, not attention-grabbing" motion posture consistent with §4.4), with `prefers-reduced-motion` respected (§20.7) by freezing on the first image rather than disabling the feature outright.
- **`video`** — a short, muted, looping background video (autoplay requires muted per browser policy; never autoplay with sound), with the static `image` mode's asset used as the `poster`/fallback for slow connections and for `prefers-reduced-motion` users (video does not autoplay for that group — falls back to the poster image, motion-safe by construction).
- **`none`** — solid `--primary` background only (Old Admin's actual current behavior), always a valid, complete configuration on its own — background media is enhancement, never a requirement.

### 20.4 Welcome content — multilingual, Configuration-driven, with a safe default

Welcome title and description are Configuration keys using the Configuration Platform's own bilingual `Translatable` convention (Blueprint Addendum B5 — the same three-way translation test already governing every other bilingual field in this system, not a bespoke i18n-only string), so a school can customize its own welcome copy per language without a deploy. Un-configured, both fall back to a sensible product default (i18n keys, not empty strings) — the page is never blank pending a school's own copy.

**Rotating motivational messages** are a small, ordered array (Configuration-backed, bilingual) cross-fading on a calm interval (recommend 6–8s, the same `fade-in` keyframe already frozen in §4.4, never a slide/wipe transition) beneath the main welcome title — an enhancement layer, empty array is a fully valid, complete state (the title/description alone suffice).

### 20.5 Maintenance mode message

Old Admin's `maintenance_mode` toggle (flagged in §M4 as needing risk-tiered confirmation on the *admin* side) has a direct, necessary counterpart on the *login* side: when the resolved Configuration state is maintenance-on, the login page replaces the standard welcome content with a clear, distinct maintenance notice (not a generic error) — explaining the system is temporarily unavailable, without exposing internal diagnostic detail (no stack traces, no environment info — that belongs solely in the authenticated Diagnostics page, §1.4). Authentication itself is not blocked by maintenance mode for accounts holding the elevated permission that can toggle it off (mirroring Old Admin's own `NewspaperEnabledRoute` precedent of always leaving the door open for the person who can turn a flag back on) — everyone else sees the notice in place of the login form.

### 20.6 Version footer & copyright

A small, permanently-visible footer inside the brand column: app version (build-time-injected, never hand-maintained), and a copyright line using the same bilingual Configuration-backed pattern as Old Admin's `copyright_text_ar`/`copyright_text_en` fields (§1.3) — ported as a genuine Configuration Platform key this time, not a legacy dual-field settings-form artifact.

### 20.7 Accessibility

- The background layer (image/slider/video) is `aria-hidden` and purely decorative — screen readers never announce it, and its cross-fade/rotation never steals focus.
- `prefers-reduced-motion: reduce` freezes the slider on its first frame and suppresses the video background entirely (falls to the poster image) and disables the motivational-message cross-fade (shows the first message statically) — every motion enhancement in this experience has a named, correct reduced-motion fallback, not just the ones convenient to handle.
- Full keyboard operability of the form column exactly as specified in §1.6/§9 (focus-visible rings, no keyboard traps, logical tab order starting in the form, never requiring a user to tab through decorative brand-column content first).
- Sufficient contrast between the gradient overlay and any welcome text is a hard requirement of the overlay's own default styling, not left to a school's background-image choice to accidentally violate — the overlay must guarantee WCAG AA contrast for overlaid text regardless of the underlying image's own colors.

### 20.8 Responsive layout

Specified in full in §20.1; the concrete, testable acceptance criterion is: **no viewport width ever loses the school's brand identity entirely** — the confirmed Old Admin failure this section exists to fix.

### 20.9 Post-authentication flow — Loading Experience → Workspace Bootstrap → Dashboard

This flow is itself a frozen Design System sequence, not an implementation detail:

1. **Loading Experience** — immediately on successful credential submission, the form column transitions (via the existing `fade-in` keyframe, never a jarring cut) to a branded loading state — the school logo (already resolved and cached from the login page itself, zero additional flash) with a calm, indeterminate progress indicator. This replaces Old Admin's generic, unbranded centered `LoadingState` (§1.1's confirmed gap: "no skeleton of the eventual layout") with something that still carries brand identity through the transition.
2. **Workspace Bootstrap** — the moment `/api/v1/me` and `/api/v1/workspaces` resolve (New Admin's existing, already-built endpoints), the loading experience's copy updates to reflect what's actually happening (a "setting up your workspace" class of message, not a spinner with no context) while `useVisibleWorkspaces` computes the user's actual permitted set — this is the natural, already-existing seam in New Admin's architecture (§8) where this step belongs; no new backend mechanism is required.
3. **Dashboard** — the resolved workspace set renders, and if it is empty, New Admin's already-built `EmptyWorkspaceState` (§1.1 — a component Old Admin never needed to build) takes over immediately, never a blank page.

Every transition in this three-step sequence uses the same `fade-in` keyframe (§4.4) — the whole first-impression journey, from login through to a populated dashboard, should read as one continuous, calm, branded experience, not three visually disconnected screens.

---

## 21. Installer Experience (Documented, Not Implemented)

Two genuinely different experiences, explicitly separated per instruction. Neither is implemented by this document — this section exists so the distinction is decided and recorded before either is ever built.

### 21.1 Installation Wizard

**Audience.** A technical operator (DevOps, a systems integrator, AlphaSchool's own deployment engineer) provisioning a brand-new dedicated instance (this ERP's confirmed commercial model — dedicated-instance-per-customer, ADR-0006). Not a school administrator, not a teacher, never a student-facing surface.

**Purpose.** Get the application from "code deployed, nothing configured" to "a working, empty, securely-provisioned instance ready to hand off." This is infrastructure bring-up, not business configuration.

**Scope (indicative, not final — a future dedicated design pass owns the real specification):**
- Environment/database connectivity verification (mirroring Diagnostics' own `connectivity.database`/`connectivity.cache` checks, §1.4 — the same health-check vocabulary, reused rather than reinvented).
- Application key / encryption key generation (the same mechanism the Credential Vault's `encrypted:array` cast already depends on, Phase 2).
- Initial migration run.
- Creation of exactly one initial super-admin account (the same `is_super_admin` `Gate::before` bypass mechanism already frozen in the backend, `DOMAIN_BLUEPRINT.md` §8) — deliberately minimal, a login credential only, no school identity yet.
- Storage tier / disk driver confirmation (Media Architecture's three-tier disk model, `docs/DOMAIN_BLUEPRINT.md` §12) and, where applicable, initial Provider Registry credentials for storage (R2StorageProvider, Phase 2) — this is the one place the Installation Wizard and the Provider Registry backend genuinely intersect.

**Visual treatment.** Minimal, functional, safe — closer to a command-line-adjacent setup flow than a branded consumer experience. It should not attempt Digital Experience branding at all (§8.1/§20), since at this point in the lifecycle no school identity, logo, or Configuration values exist yet to brand it *with* — using placeholder/generic AlphaSchool product branding only, explicitly not the eventual school's own identity.

**Never confuse with**: the First-Time School Setup Wizard below. A technical operator running this wizard is not necessarily the same person, role, or even organization as the school staff who will later configure the school's own identity — conflating the two would force a DevOps engineer through school-branding questions they cannot answer, and force a school administrator through database-connectivity questions they should never have to see.

### 21.2 First-Time School Setup Wizard

**Audience.** The school's own super-admin, immediately after the Installation Wizard hands off a working-but-empty instance, or at any later point an operator chooses to (re-)run guided setup.

**Purpose.** Turn an empty, generic instance into *this specific school's* branded, structurally-ready ERP. This is business/pedagogical configuration, not infrastructure.

**Scope (indicative, not final):**
- **School identity** — name (bilingual), logo (light/dark, feeding directly into §20.2's Configuration keys — this wizard is the natural *authoring* surface for the values the Login Experience *consumes*), primary brand color (the `--primary` token override, §4.1's own "organization brand-color slot," ADR-0006), the Digital Experience capability's own Configuration keys (§8.1: Branding, Login Experience, Domains, SEO, Social Presence).
- **Branch setup** — at least one initial Branch (the multi-branch primitive this entire ERP's permission/Configuration-altitude model already assumes, ADR-0018 Decision 4's Global→Branch chain).
- **Academic structure bootstrap** — initial academic year/term, if the Academic module is licensed for this instance (Workspace licensing, `docs/ADMIN_PLATFORM.md`'s own "Organization-level licensing determines which workspaces are even possible").
- **Initial roles/permissions provisioning** — beyond the one bare super-admin account the Installation Wizard created, guided creation of the school's real initial staff accounts and role assignments (Permission Groups, `DOMAIN_BLUEPRINT.md` §8).
- **Provider/Integration onboarding** — a guided, low-pressure on-ramp into the Provider Registry (Phase 2) for the integrations a school is likely to want immediately (email/SMTP at minimum, matching Phase 2's own SMTP proof provider) — framed as "connect your email" business language, never exposing Vault/credential-field internals directly.

**Visual treatment.** This *is* a Design System surface — full Digital Experience branding is deliberately unavailable at the start of this wizard (nothing is configured yet) but the wizard itself uses the standard AlphaSchool product identity (§4) and is the natural first real implementation of the **Wizard/multi-step form pattern** already named as a required Enterprise addition (§14) — guided, step-indicator-driven, save-progress-and-resume-capable (a school administrator should never have to complete this in one sitting), each step a genuine `SettingsSection`-shaped card (§7 Settings/Configuration Template) rather than a generic unstyled form.

**Never confuse with**: the Installation Wizard above. This wizard assumes a working, secure, already-provisioned instance and a logged-in super-admin — it has no business ever asking about database connectivity, encryption keys, or storage drivers.

**Explicitly out of scope for this document**: field-level specification, exact step sequencing, and screen-by-screen layout for either wizard. Per instruction, this section documents that the two experiences exist, are distinct, and roughly what each owns — a dedicated design pass (following this same evidence-based discipline) is the correct venue for the full specification, at the point either is actually scheduled for implementation.

---

## 22. Document Freeze Declaration

**This document is now FROZEN as of this revision.** Per explicit approval: the document approved in principle in its draft form, plus the three decisions recorded in §8.1 (navigation naming), §19 (iconography system), and §20 (login experience), plus the documented-not-implemented Installer Experience split (§21), together constitute the **official AlphaSchool ERP Administration Frontend Design System**.

Consistent with this project's standing discipline for every frozen document: no further design discussion is expected or warranted unless implementation exposes a genuine usability problem — at which point the resolution follows the same rule already proven repeatedly across the backend (Phase 1's deptrac gap, `PermissionDoesNotExist`, `model_has_roles.branch_id`; Phase 2's `ProviderSlotDefinition` permission fields, `MailFake::raw()`) — the smallest possible documented amendment, appended here, never a reopened redesign discussion.

**Explicit instruction carried forward into implementation**: do not imitate Old Admin pixel-by-pixel. Every component mapping in §10, every "keep almost identical" entry in §12, and every token in §4 exists to preserve *identity and feeling*, not to reproduce Old Admin's exact markup, exact class strings, or its own internal inconsistencies (§4.3's dead radius-0 policy chief among them). Where this document identifies a gap between what Old Admin *did* and what it *should have done* (§2's ten UX mistakes, §3's Experience Improvements, §17's technical debt), AlphaSchool ERP implements the improvement, not the inherited flaw — "familiar in the first five minutes, noticeably better after five" (§0) is the acceptance bar for every screen built against this document, not merely "looks the same."

Implementation begins per the sequencing in §15, starting with design tokens alone (§15 step 1), now including the frozen icon library (§19) and informed by the frozen Login Experience specification (§20) as the natural first real proof of the full token system working end-to-end on a single, self-contained, high-visibility page.

---

## 23. Phase B Revision — Icon Sizing & Radius Amendment (2026-07-19)

Per §22's own stated amendment trigger ("implementation exposes a genuine usability problem"), two smallest-possible amendments to the frozen §4.3 and §19.3, both raised during real App Shell (Phase B) implementation review, not a reopened redesign discussion.

### 23.1 Icon Sizing — supersedes §19.3

**Problem.** §19.3's scale was tuned for density, not for AlphaSchool ERP's actual primary users — school principals, administrative staff, finance/HR employees, and teachers, many spending 6–8 hours/day in the system, a meaningful share older users wearing corrective lenses. Readability and recognition speed outrank compactness for this audience.

**New scale**, replacing §19.3's table (defined in `admin/src/lib/icon-sizes.ts` as the single source of truth — `ICON_SIZE.dense` / `.default` / `.prominent` — never a raw `size-*` class on a semantic icon):

| Tier | Size | Tailwind | Applies to |
|---|---|---|---|
| **Dense** | 20px | `size-5` | Table/dense-data cells, inline row actions, toolbar buttons, user-menu items, form-field icons |
| **Default** | 24px | `size-6` | Sidebar nav, topbar actions (search, command palette, notifications, theme/language), primary buttons |
| **Prominent** | 28px | `size-7` | Status panels (Loading/Error/Empty state), dashboard/KPI card icons |

Click targets and spacing were re-verified after the increase (Phase B revision browser pass, 2026-07-19): icon buttons hold at a 40px (`size-10`) hit target, inputs at `h-10`, avatar at `size-9` — all comfortably above the icon's own bounding box at every tier, so the larger icons did not force a corresponding hit-target increase.

**Real bug found and fixed in the same pass, not merely a size change**: `[&_svg]:size-4`-style descendant rules on `Button` and `DropdownMenuItem` have *higher* CSS specificity than a plain `size-*` class on the icon element itself, silently overriding any component-level icon explicitly sized per this scale. Both now use the `[&_svg:not([class*='size-'])]:size-N` guard — a default for icons that don't specify a size, never an override for ones that do. Any future component following the same descendant-default pattern must use the same guard.

Small status/count indicators (unread badges, unread dots, breadcrumb chevron separators) are deliberately outside this scale, unchanged — they are not primary icons and were never governed by §19.3.

### 23.2 Radius — supersedes §4.3's radius paragraph and its `--radius`/`sm`/`md`/`lg`/`xl` calc-derived scale

**Problem.** §4.3's adopted scale (10px base, `calc()`-derived tiers up to `--radius-xl` ≈ 14px, with several App Shell components independently reaching for Tailwind's own `rounded-2xl`/`rounded-3xl` defaults on top of that) reads as softer and more consumer-oriented than the "professional, stable, enterprise-grade, information-focused" surface this product should present.

**New scale** (`admin/src/index.css`'s `@theme` block, four independent flat tokens, none derived from the others):

| Token | Value | Tailwind utility |
|---|---|---|
| `--radius-none` | 0px | `rounded-none` |
| `--radius-sm` | 4px | `rounded-sm` |
| `--radius-md` | 6px | `rounded-md` |
| `--radius-lg` | 8px | `rounded-lg` |

`rounded-lg` (8px) is the ceiling — no `xl`/`2xl`/`3xl` tier is defined, and every component previously using one was remapped to a tier above. `rounded-full` remains available, unchanged, for genuinely circular elements (Avatar, unread-count badge, unread dot, Badge, user-menu trigger) — a separate design-language decision, not an exception carved into this scale.

Per-surface application, superseding any conflicting radius choice made during initial Phase B implementation:

| Surface | Tier |
|---|---|
| Sidebar (outer panel) | none |
| Navigation items (sidebar links, group headers, collapse toggle) | `sm` |
| Tables, page containers | none |
| Cards, dashboard widgets | `sm`–`md` (4–6px, never more) |
| Dropdowns, popovers, tooltips, skeleton placeholders | `md` |
| Dialogs, the Command Palette | `lg` (the enterprise ceiling — "moderate," never larger) |
| Buttons, inputs, selects | `md` (already-correct, unchanged) |

### 23.3 Status

Both amendments are implemented and browser-verified (real login, LTR/RTL, light theme) against the running App Shell as of 2026-07-19. This section, together with §19.3 and §4.3's radius paragraph (left in place for historical record of the original reasoning, now superseded by §23.1/§23.2), is the complete, current statement of these two token systems. No further icon-sizing or radius discussion is expected unless implementation exposes a new, genuine usability problem — the same standing rule as §22.

---

## 24. Global Context Model — Organization, Branch, Academic Year (Frozen 2026-07-19)

A new append-only addition, not a supersession — no prior section of this document specified a Global Context concept. Reached through a dedicated UX review (not implemented in code as of this freeze; see §24.8) that deliberately challenged the original proposal before converging here.

### 24.1 Model

Organization, Branch, and Academic Year are **Global Application Context**, not page-level filters. A user navigating between Students, Attendance, Grades, Timetable, and Finance stays inside the same working context across all of them without re-specifying it per screen — both what they see by default, and what a newly-created record targets by default (a new attendance entry, a new grade, a new timetable slot all need an implicit "which year" without asking every time).

### 24.2 Context Control

A single, unified Context control lives in the Topbar — e.g. "AlphaSchool Amman • Main Branch • 2025–2026" — opening one panel to adjust any of the three, rather than three independent dropdowns competing for space. One mental model ("where am I right now"), and it scales cleanly if a future context dimension is ever added. Organization and Branch are scope axes; Academic Year is the only time axis among the three, which is why §24.3–§24.5 apply to Year specifically and not to Organization/Branch switching.

### 24.3 Switching Behavior

Selecting a different Academic Year inside the Context Panel does not apply instantly. It surfaces a lightweight, explicit **Switch** step inline within the same panel — never a separate modal, never a blocking popup:

```
Switch Context
Current:  2025–2026
          ↓
          2024–2025
[Cancel]  [Switch]
```

This is deliberately proportioned: heavier than a plain filter (correct — Global Context recontextualizes every open workspace and tab at once, which is a genuine disorientation risk even though it carries no data risk on its own), lighter than a blocking confirmation dialog (correct — browsing a past year is safe by construction; see §24.5 for where real protection actually lives).

### 24.4 Working vs. System Active Academic Year

Whenever the selected (Working) Academic Year differs from the system's actual current (Active) year, the Topbar always shows both, distinctly, using calm/muted styling — never `--warning`/`--destructive`. A user must never be able to look at the shell and mistake a historical Working Year for the system's actual current year, whether because they switched it themselves earlier in the session or because they're looking at a colleague's screen. Exact layout (stacked two-line label vs. a "Viewing Historical Year" caption) is an implementation-time visual decision, not a UX-model constraint.

### 24.5 Write Boundary Protection

Protection lives at the point of mutation, not at the Context Switch — this is the central UX principle of this section. Browsing a historical Academic Year is always low-friction and safe; a create/update/delete targeting a non-active year is where real protection applies:

1. **Permission gate** — a distinct `modify-historical-records` permission, separate from `view-historical-records`, reusing the view/edit permission-split convention already frozen elsewhere (ADR-0018 Decision 9). Without it, the mutating control is disabled with an explanatory tooltip, never shown-then-warned.
2. **Risk-tiered confirmation** — reusing the existing risk taxonomy (reversible / destructive / high-blast-radius, §M4/§M7/§M9) rather than one generic "this is historical" dialog regardless of the mutation's actual size or reversibility.
3. **Approval routing for the top tier** — through the already-built Approval Engine, with a mandatory recorded reason, exactly how every other high-blast-radius action in this system already works. No bespoke, weaker, frontend-only mechanism invented specifically for Academic Year.

### 24.6 Persistence

Global Context (Organization, Branch, and Academic Year together, as one unit) persists via the same mechanism already established elsewhere in the App Shell (Zustand + `persist`, matching `sidenav-store.ts`'s pattern) for the duration of the authenticated session — page reloads and additional tabs under the same login retain the selection. **On every fresh login, Global Context resets to system defaults** (the user's default/primary Branch, the system's current Academic Year), never restored from a prior session's selection.

This is a first-principles decision, not a port of an existing Branch-context policy — none exists. A dedicated codebase check (2026-07-19) confirmed the Admin Platform Foundation has no branch-switcher concept today: permissions are computed as a union across all of a user's branches specifically because no "current branch" exists yet (`User.php`'s own docblock states this explicitly), no backend endpoint returns a current branch/team, and no frontend store holds one. Global Context is therefore the first implementation of this concept for all three dimensions together — reset-on-login was chosen specifically because it closes the same "stale historical context silently surviving into a new session" risk that §24.5's write-boundary protection exists to guard against, at zero practical cost (most logins default to the current Branch and current Year regardless).

### 24.7 Branch / Academic Year Validity

The application must never sit in an invalid Branch/Academic Year pairing. If a Branch switch leaves the currently-selected Academic Year unavailable for the new Branch, the system automatically corrects to that Branch's active Academic Year — immediately, without a blocking dialog (this is a system-initiated validity correction, not a deliberate user switch, so §24.3's explicit-switch friction does not apply) — and surfaces a brief, non-blocking inline notice in the Context Panel (e.g. "Academic Year switched to 2025–2026 — not available for [Branch Name]"), so the correction is always visible, never silent.

### 24.8 Status

This section is a frozen **design decision**, not yet implemented in code — no Global Context control, store, or write-boundary guard exists in `admin/` as of this freeze. It is ready to be picked up as its own implementation slice, sequenced at the point the product owner chooses (not necessarily inside Phase B's remaining closeout). Phase B (App Shell: Sidebar, Topbar, Breadcrumb, Notification Center, Search, Command Palette, and their 2026-07-19 icon/radius revision, §23) is considered complete as implemented; Global Context Model is a separately-tracked, frozen-but-unbuilt addition, not a blocker on Phase C.

### 24.9 Scope Boundary — Global Context Is Not an Authorization Mechanism

Global Context defines the application's default **working** context only — what a user sees by default and what a new record targets by default. It is a UX convenience layer, never a security boundary, and it does not substitute for, weaken, or bypass any authorization rule, business rule, or approval workflow defined elsewhere in this architecture:

- Selecting a Branch in the Context Panel does not grant access to that Branch. Whatever branch-scoped role/permission check governs that Branch still applies in full, entirely independent of what's currently selected as Working Context.
- Selecting a non-active Academic Year does not, by itself, grant `modify-historical-records` (§24.5). That permission, its risk-tiered confirmation, and Approval Engine routing apply exactly as specified regardless of the currently-selected Working Year — Global Context supplies a mutation's *default target*, never a reason to skip evaluating whether that mutation is allowed.
- Every mutation, whatever Global Context it inherits its default scope/year from, remains subject to the same permission checks, validation, and approval workflows any other write in the system already goes through. Global Context changes what a form is pre-filled with; it changes nothing about whether submitting that form succeeds.

This clause exists specifically to foreclose a plausible but wrong implementation shortcut: treating "currently selected Branch/Year" as equivalent to "authorized for that Branch/Year." They are two independent systems that happen to share one UI surface — the Context Panel supplies defaults, the permission/approval system decides what's actually allowed — and must never be conflated into one.

---

## 25. Dashboard Shell (Frozen 2026-07-19)

Reached through a dedicated design review that deliberately rejected a framework-first framing ("a container for future widgets") in favor of a user-first one: a school principal, accountant, HR employee, or teacher should feel the Dashboard answers "what do I need to know and do right now" — even though, as of this freeze, almost nothing that could genuinely answer that question yet exists as real backend capability (§25.5). This phase builds the shell only: layout, composition, the widget registration model, and empty-state discipline. It owns no business domain and no backend work (§25.3).

### 25.1 Composition

`HomePage` becomes the Dashboard shell — extended, not replaced by a second landing page. Three existing, independently-proven mechanisms compose on one page, deliberately not unified into one new abstraction (they already have different shapes, and forcing them together now would be speculative):

1. **Quick Actions** (top) — the existing Phase B registry, previously feeding only the Command Palette; this phase adds it as a second surface here.
2. **Registered Widgets** (main area) — the one genuinely new mechanism: a registration model mirroring `WorkspaceDefinition`'s own pattern, so a future workspace can *optionally* contribute a widget to the shared landing Dashboard. This phase builds the registry, the grid, and permission-aware rendering only — never a specific widget.
3. **Notifications** (compact summary) — the existing Phase B hook, a denser second presentation of the same honest-empty-state `NotificationCenter` already proves.

**Workspace Launcher** (the existing tile grid) stays exactly as it is today, unchanged, anchoring the page beneath the above.

### 25.2 System Initialization vs. Operational Empty States

Two genuinely different empty conditions, requiring two different messages, distinguished by a precise, already-available signal rather than a single generic "nothing here" state:

| State | Signal | Meaning | Message |
|---|---|---|---|
| **System Initialization** | `getRegisteredWorkspaces()` (the local, static, build-time registry) is empty | No workspace module has been *built into this deployment* at all — a deployment-level fact, true for every user, not a permission gap | A calm, singular onboarding message: widgets, quick actions, and notifications will appear automatically as modules are enabled. Not fake content or a placeholder widget — a genuine product-level onboarding state, replacing the entire page as its sole content, exactly as `EmptyWorkspaceState` does today. |
| **Operational Empty State** | `getRegisteredWorkspaces()` is non-empty, but `useVisibleWorkspaces()` (the server-filtered, per-user list) returns empty | Workspaces exist in this deployment; this specific user isn't licensed/permitted for any of them | The existing `EmptyWorkspaceState` copy, unchanged — "your account isn't licensed or permitted... contact your administrator" is correct here, specifically because it is *not* true during System Initialization. |

Conflating these was a real risk worth naming explicitly: once real workspaces ship, telling a fresh installation's own Super Admin to "contact your administrator" — when they *are* the administrator, mid-setup — would be actively wrong, not merely unpolished. The two states must never share one message.

Within an *individual* section (Quick Actions, Registered Widgets, Notifications) once at least one workspace is visible, each section's own existing empty-state convention applies independently and quietly (Quick Actions/Widgets render nothing at zero, matching the "correct with zero" bar already set in Phase B; Notifications keeps its existing "you're all caught up" copy) — these are ordinary Operational Empty States, not System Initialization, and do not need the onboarding message repeated per-section.

### 25.3 Design Principle — Presentation and Composition Only

**The Dashboard owns presentation and composition only. Every business capability contributes exclusively through registration.** The shell defines *how* a workspace, a widget, a quick action, or a notification appears — layout, grid behavior, permission-aware rendering, empty-state rules — and never *what* business data appears. No phase that touches the Dashboard shell may add a named, domain-specific section (a Finance KPI, an Approvals list, a Schedule view) directly into the shell's own code; every such capability must arrive as a registration from its owning module, exactly as workspaces already do via `WorkspaceDefinition`. This is the same extension-point discipline already governing `AppShell`/nav/routing (ADR-0015 Decision 4), applied to the Dashboard specifically because "operational from day one" is a strong pull toward embedding real-seeming content directly into shell code — a pull this principle exists to resist.

### 25.4 Status

Frozen design, not yet implemented in code as of this freeze. `Registered Widgets` is the only genuinely new mechanism this phase adds; `Quick Actions` and `Notifications` are existing Phase B primitives gaining a second surface. Implementation is scoped to the shell only — no backend work, no first widget, no Approval Engine list endpoint (§25.5) — per §25.3.

### 25.5 Deferred, Not Owned By This Phase

A dedicated capability check (2026-07-19) found that most of the sections a genuinely operational Dashboard would need do not exist as real backend capability yet, and this phase deliberately does not build any of them:

- **My Pending Approvals** — the Approval Engine (Core) is real and mature, but has no list/query capability at all, not even "list all approval requests," let alone one scoped to the current user. The most likely first real widget once that gap closes, but that backend work belongs to whichever phase owns it, not this one.
- **Recent Activity** — Spatie Activitylog is genuinely recording data across the codebase, but no API exposes any of it yet.
- **Today's Schedule** — no Timetable/Scheduling module exists at all.
- **Critical Alerts** — no mechanism beyond Notifications itself (already empty-state-only today) — not a distinct real capability.
- **Global Context** — deliberately not a Dashboard section; it is Topbar chrome (§24), and duplicating it here would render the same information twice.

None of these are blocked by this phase's design — §25.1's registration model is exactly the mechanism each will use to plug in once its owning module is ready.

---

## 26. Administration Workspace — Reference Implementation (Frozen 2026-07-19)

Reached through a dedicated UX/product-design review across information architecture, navigation, settings hierarchy, page templates, permission model, configuration philosophy, layout patterns, empty states, responsive behavior, and accessibility, before any code — same discipline as every prior phase.

### 26.1 Purpose and Scope

Administration Workspace is the reference implementation for **configuration-oriented** workspaces specifically, not for every future workspace. Entity-CRUD workspaces (Students, Finance, HR, Library) are a different shape — lists, detail records, create/edit forms — already served by the `DataTable`/`Form` frameworks (Phase 13), and need their own reference proof separately; conflating the two would either pull this workspace toward concerns it doesn't have or leave the entity-CRUD pattern under-designed. First implemented child: **Configuration Platform** (§8.3's nine-child Administration group), the most backend-mature capability (Phase 1).

### 26.2 Information Architecture and Naming

Honors §8.3 verbatim — `Administration` (group) → nine children, with only Configuration Platform registered in this phase; the other eight remain unregistered until their own phases, the same "zero is correct" discipline as the Workspace registry itself. Within Configuration Platform, settings are organized by category (Identity/OTP today; Media/Storage, Notifications/Email later).

**Naming is deliberately two separate things.** "Configuration Platform" is the architectural capability name — it matches the Administration Platform Blueprint's own vocabulary and stays fixed in code, docs, and the backend contract. The end-user-facing navigation label is a distinct, independently-evolvable UX decision, resolved through the existing `labelKey`/i18n mechanism every other workspace already uses (e.g. it may read "System Settings" in the UI) — never hardcoded to the architectural name. A strict one-to-one mapping is maintained between the two; only the label's wording is free to evolve without touching the architecture, the registry key, or any backend contract.

### 26.3 Navigation Model

Two-pane: a search-augmented category rail (left) + the selected category's form (main). Breadcrumb: Home → Administration → [nav label] → [Category], via the existing multi-level `Breadcrumb`, unchanged.

### 26.4 Settings Hierarchy

Default→Global→Branch altitude chain from `SettingsResolver` — confirmed against `ConfigurationScopeContext`'s own docblock during Phase E-B implementation to have no "User" altitude at all; User Preferences are a deliberately separate, lower-ceremony mechanism outside this resolver, not a fourth rung on this chain. A Branch-scoped field's edit view honors the currently-selected Branch from Global Context (§24) — explicit coherence between the two systems, never a bypass (§24.9 applies in full). Each field shows which altitude it is *currently resolving from* ("Using the global default" / "Set globally" / "Set for this branch") via the resolver's real `resolvedFrom` trace (shipped in §26.13's Phase E-B).

### 26.5 Page Templates

`SettingsCategoryList` (rail) + `SettingsCategoryDetail` (card-sectioned form, `StickyActionBar` save, per the already-frozen §10 note) + a generic field renderer keyed off data type, reusing the existing `TextField`/React Hook Form + Zod convention. Approval-gated writes reuse the risk-tiered confirmation taxonomy (§M4/§M7/§M9) already proven for Global Context (§24.5) — a second consumer of the same mechanism, not a new one.

### 26.6 Permission Model

Real, seeded permission strings exist (`identity.view-otp-settings`/`identity.configure-otp-settings`, the view/edit split per ADR-0018 Decision 9), enforced server-side by the real adapter API (§26.13's Phase E-B) — not a client-side bypass. View-gating bypasses for `is_super_admin`, matching `WorkspaceAccessResolver`'s coarse nav-gating philosophy; edit-gating deliberately does **not** bypass for `is_super_admin` in either the `canEdit` flag or the write endpoint itself, both deferring entirely to `SettingsResolver::assertCanEdit()`'s existing, unmodified behavior — so `canEdit: true` never promises more than a subsequent write would actually allow. View-but-not-edit renders disabled with an explanatory note, never hidden. A category with zero visible permitted settings does not appear in the rail at all (§8.4, extended one level deeper).

### 26.7 Configuration Philosophy

This workspace's entire purpose is showing *real* resolved values — unlike Login's wordmark fallback, there is no meaningful default to fall back to. Honesty here means real data or an explicit "not yet connected" state, never a plausible-looking mock.

### 26.8 Reusable Layout Patterns

The two-pane rail+content shape, `StickyActionBar`, card-sectioned form groups, and the existing `WorkspaceHeader` — deliberately reusable by Administration's other eight children and by any future workspace's own settings page.

### 26.9 Empty States

Three distinct conditions, not one generic state: zero categories registered system-wide (a System-Initialization-style message, §25.2's precedent); a category exists with no Branch-level override set ("using Global default" — a real, non-broken state, not an error); no permission to view a category (absent from the rail entirely, per §26.6).

### 26.10 Responsive Behavior

Two-pane desktop collapses to a single-pane drill-down on mobile (category list → detail → back) — never a hidden rail, matching the Sidebar (§5) and Login brand column (§20.1) precedent.

### 26.11 Accessibility

Category rail as a real nav landmark (`aria-label`, matching `Breadcrumb`/`SideNav`'s existing convention). `StickyActionBar`'s Save button needs a deliberately sensible keyboard tab order on long forms — sticky visual positioning alone does not fix tab order.

### 26.12 Registration Principle

Administration's children are discovered **exclusively** through the existing `WorkspaceDefinition` registration mechanism (§8.2's `group` field) — the same registry every other workspace already uses, never a bespoke Administration-only mechanism. The Administration Workspace shell itself never assumes any specific child is present: it must render correctly with zero children registered (the Workspace registry's own zero-is-correct discipline, unchanged), with only Configuration Platform registered (this phase's actual state), or with all nine eventually registered — no conditional logic anywhere keyed to a specific child's presence. This is ADR-0015 Decision 4's extension-point discipline, applied one level deeper, inside the Administration group itself.

### 26.13 Implementation Plan

- **Phase E-A (frontend infrastructure)**: layout, navigation, page templates, permission-aware rendering, responsive behavior, accessibility, and empty-state handling — verified against a temporary, fully-reverted fixture (the same discipline already proven for Phase B's Sidebar and Phase D), since real data flow does not exist yet.
- **Phase E-B (Configuration Platform integration)**: a thin adapter-layer REST API exposing `SettingsResolver`/`ConfigurationRegistry` (and later `ProviderManager` for Provider Registry) — reusing existing services verbatim, no business-logic changes, HTTP wiring only. Sequenced after E-A, explicitly scoped, preserving the standing rule that backend capability is exposed before the UI depends on it.

**API stability principle.** Once E-B ships it, the REST API surface becomes the stable public contract between frontend and backend — its shape (request/response structure, field names, error format) is what the frontend depends on and must not change without a deliberate, versioned decision. The internal PHP services behind it (`SettingsResolver`, `ConfigurationRegistry`, `ProviderManager`) remain free to evolve, refactor, or be reimplemented entirely, as long as the contract they serve stays stable — the adapter/controller layer is the boundary that insulates the frontend from internal implementation churn, never a pass-through that couples the two.

**Phase E-B shipped (2026-07-20).** `ConfigurationController` (`GET /api/v1/administration/configuration/categories`, `GET .../categories/{key}/settings`, `PATCH .../categories/{key}/settings/{fieldKey}`) is a thin adapter over `SettingsResolver`/`ConfigurationRegistry` verbatim, per this section's own principle — no business-logic changes. The contract is defined once, in `@alphaschool/contracts` (ADR-0023), and consumed by both `real-configuration-provider.ts` and the Pest Feature test suite. `/api/v1/workspaces` (§26.6/ADR-0018) was also wired to real permission-based visibility in this same phase — `WorkspaceAccessResolver` previously returned `[]` unconditionally, which would have made this workspace unreachable regardless of E-B's own work.

### 26.14 Status

Frozen design. Phase E-A and Phase E-B are both shipped and verified — real backend, real Pest Feature tests, real browser E2E check (login → Overview Grid → category detail, real OTP field values, correct `resolvedFrom`/`canEdit` states, RTL and dark mode, no console errors). Configuration Platform's `ConfigurationDataProvider` is genuinely connected, not a fixture; §26.7/§26.9's "not connected" state remains correct only for a deployment that never registers this workspace's provider. Remaining Administration children (eight of nine) are still unregistered, per §26.12.

### 26.15 Overview Grid Refinement (2026-07-19)

An append-only amendment to §26.3/§26.5, not a supersession of the two-pane rail+content interface itself — that interface is unchanged, it simply stops being the first thing a user sees.

System Settings now lands on a responsive card-grid overview (3 columns desktop / 2 tablet / 1 mobile) before the rail+detail interface, each card representing one configuration area: a soft outline icon, the area's name, a single status indicator (`Ready` / `Needs Setup` / `Error`, shown as a small colored badge — never a chart, counter, or stat), and an optional one-line secondary note (e.g. the active provider). The entire card is clickable and opens the existing two-pane interface with that category pre-selected; a back affordance (now shown on every breakpoint, not mobile-only as originally built) returns to the overview.

Deliberately modeled on modern Settings-surface precedent (Apple/Linear/Notion/Stripe), not an analytics-dashboard precedent — this is why status is a single small badge, never a number, and why no chart or counter is permitted on a card regardless of what data becomes available once Phase E-B ships. `SettingCategory` (§26.13) gained `icon`, `status`, and an optional `secondaryLine` to support this — an additive extension of the same provider contract, not a new mechanism; Phase E-B's job is unchanged, only the shape of what it returns grew by three fields. Visual treatment stays inside the frozen enterprise radius scale (§23.2) — cards use `rounded-md`, not a larger radius, so this refinement does not reopen the flatter, more enterprise-appropriate surface §23 already established.

### 26.16 The Overview Grid Pattern (2026-07-19)

A same-day amendment to §26.15, superseding its grid-column count — the card content model (icon, name, one status badge, optional secondary line, no charts/counters/stats) and the card→two-pane→back interaction are unchanged.

**Grid**: 6 columns on large desktop, 4 on medium, 2 on tablet, 1 on mobile — supersedes §26.15's 3/2/1. Verified live at exactly these four breakpoints.

**A named pattern, not a list of exceptions.** The System Settings overview card treatment — square corners, soft elevation, a subtle hover lift — is not a one-off deviation from §23.2/§4.4 tolerated for this single page. It is the **Overview Grid Pattern**: a distinct, reusable UI pattern for high-density navigation surfaces, alongside (not instead of) the standard Card treatment those two sections already govern.

| | Standard Cards (§23.2) | Overview Grid Pattern (this section) |
|---|---|---|
| Radius | `sm`–`md` (4–6px) | `rounded-none` — square corners are this pattern's own defining trait, not an overshoot of the standard scale |
| Hover motion | none (§4.4's calm, flat motion language) | a subtle lift (`translateY(-2px)`) + strengthened shadow, `transition-[transform,box-shadow]` only |
| Elevation | `shadow-soft` at rest | `shadow-soft` at rest, `shadow-soft-lg` on hover — both existing §4.3 tokens, no new shadow value |
| Intended use | detail pages, forms, dashboard widgets — anywhere content is read or edited | dense grids of clickable navigation entries — System Settings today, plausibly Provider Registry / Integrations / AI Providers later, anywhere an "overview of areas to enter" is the page's whole job |

§23.2 and §4.4 remain exactly as frozen for every other card and every other hover interaction in the product — this section adds a second, named pattern for a specific surface shape, rather than carving a hole in the first one. Any future page reaching for square corners or a hover lift should ask whether it is genuinely an overview/navigation grid (and therefore this pattern) or a detail/content surface (and therefore standard Cards) — the two are deliberately kept visually distinct so a user can tell which kind of page they're on at a glance.

A fourth status value, **`Disabled`** (a muted badge, matching the existing `Badge` `muted` variant — no new color introduced), was added to `SettingCategoryStatus` for a capability that exists in the taxonomy but isn't reachable yet — the card renders non-interactive (`disabled`, reduced opacity, `cursor-not-allowed`) rather than clickable-but-pointless.

Colors and shadow reuse existing tokens verbatim — no new color or shadow value was introduced. Dark-mode `--background`/`--card` already produce the requested "dark charcoal, cards slightly lighter" relationship; `shadow-soft`/`shadow-soft-lg` (§4.3) already produce a soft, non-glowing shadow, reused directly rather than hand-rolling a new one-off value.

## 27. Provider Registry — the second Administration child

Reached through a dedicated design review against the real Phase 2 backend (`ProviderManager`, `ProviderCredentialVault`, `HealthCheckRunner`, `ProviderRegistry::sync()`), not assumed to be a copy of §26 — Provider Registry's actual shape differs from Configuration Platform's in three ways significant enough to change the design, not just the data source.

### 27.1 Purpose and Scope

The second implemented child of §8.3's nine-child Administration group (`Configuration Platform` ✅ → `Provider Registry` → …), reusing every proven mechanism from §26 (Overview Grid, `WorkspaceHeader`, `StickyActionBar`, the permission model's view/edit asymmetry, real-data-only philosophy, Docker environment) rather than re-deriving them — the explicit reason this child was sequenced immediately after Configuration Platform and before any new business-domain workspace.

### 27.2 The Three Real Differences From Configuration Platform

1. **Credential values are never exposed, in either direction.** `ProviderCredential.credentials` is `$hidden`; Phase 2's own negative-case proofs verify a secret never appears in any model array/JSON representation. Configuration's field renderer shows a resolved *value*; Provider Registry's can only ever show whether a credential is *configured* — write-only inputs, never pre-filled with a previous value, not even masked dots standing in for a real one (there is no real one to fetch).
2. **No per-slot view permission exists.** `ProviderSlotDefinition` declares only `requiredPermissionToEdit` — unlike `SettingDefinition`'s mandatory view/edit pair (ADR-0018 Decision 9). Resolved explicitly (§27.6), not left implicit: since a slot's *metadata* (name, owning module, health status) carries no real risk on its own — the only sensitive thing, credential values, is never returned regardless of who's asking — visibility is gated by ordinary Administration access, not a per-slot permission that doesn't exist in the schema to check.
3. **Flatter granularity.** A Configuration category holds several independently-editable fields, justifying the rail-then-detail two-pane shape (§26.3). A provider slot is one atomic, all-or-nothing credential set — `ProviderCredentialVault::write()`'s `assertCredentialShape()` requires the exact declared field set on every write, no partial saves. There is nothing to browse *within* a slot, so a rail adds a click with no destination behind it.

### 27.3 Navigation Model

**Overview Grid → direct credential form, no intermediate rail.** The Overview Grid (§26.16) is reused exactly as-is as the landing page — each card represents one provider slot (e.g. "Email — SMTP", "Push Notifications — Firebase"). Clicking a card opens that slot's credential form directly, skipping the category-rail step §26.3 uses for Configuration Platform, since there is no second level of hierarchy to browse. Breadcrumb: Home → Administration → Provider Registry → [Slot], via the same multi-level `Breadcrumb` component, one level shallower than Configuration Platform's because the rail level doesn't exist here.

In shorthand: `Configuration Platform = Overview Grid → Category → Settings Form`, `Provider Registry = Overview Grid → Provider Form` — the underlying design system (tokens, primitives, permission model, Docker environment) stays identical; each workspace's navigation depth matches its own actual data shape rather than forcing a shared template deeper or shallower than the domain warrants.

### 27.4 Data Model Exposed

Per slot: `slotKey`, a display name (`labelKey`, mirroring §26.2's naming decoupling — `slot_key` stays the fixed architectural identifier, the label is independently translatable), `owningModule`, `capabilityContract` (not shown to the end user — internal wiring only, and per §27.5's naming-branch rule, never used by the frontend to decide *anything* either), and the declared `credentialFields`.

**Amendment (2026-07-20, pre-freeze review):** `credentialFields` is not a bare list of field names. Each entry declares its own type explicitly at the backend — `{ name: string, type: 'text' | 'password' | 'secret' }` — never inferred client-side from the field's name. A frontend heuristic keyed on names like `password`/`secret`/`key` is a list that only ever grows (`client_secret`, `private_token`, `signing_certificate`, …) and silently mis-renders the day a genuinely new field name doesn't match it; the backend already knows what each field is because it's the one declaring `credentialFields` in the first place, so it says so directly. This is a real contract change from the Phase 2 scaffold's plain `string[]` shape, landing in Phase F-B (§27.13) alongside the four existing Providers' declarations.

`status` is derived from `HealthCheckRunner::check()`, with a fifth value added specifically for this workspace's overview: `healthy` → `Ready`, `unhealthy` → `Error`, no credential configured at any altitude → `Needs Setup`, `not_checkable` (a resolved Provider not implementing `HealthCheckable`) → `Disabled` (§26.16's fourth status value, already anticipated for exactly this case), and **`checking`** → *Checking…*, shown while the status fetch (initial load) or an explicit re-check is in flight. `checking` is deliberately a client-only transient state, never a value `HealthCheckRunner`'s synchronous v1 API itself returns — it belongs to the same vocabulary as the other four purely so the Overview Grid's badge component has one enum to render from, not because the backend has a fifth real state. No chart, counter, or last-checked timestamp on the card itself, matching §26.15/§26.16's standing rule against turning an overview card into a stat.

### 27.5 Page Templates

The Overview Grid component is reused verbatim from Configuration Platform — zero changes, proving §26.16's own claim that the pattern generalizes to "plausibly Provider Registry... later." A new `ProviderCredentialForm` (structurally: `WorkspaceHeader` + card-sectioned form + `StickyActionBar` Save, the same shell §26.5 already established) replaces `SettingsCategoryDetail`/`SettingField` for this workspace — its field renderer is new, not reused, because its contract is fundamentally different (no `value` prop exists to pass it). Each declared credential field renders using the `type` the backend declared for it (§27.4) — `text` as a plain input, `password`/`secret` as a masked input — never a name-based guess. All render empty regardless of type, with a placeholder reading "configured" or "not set" rather than a real value.

**Two rules added during pre-freeze review (2026-07-20), both binding on Phase F-A/F-B:**

- **The UI never branches on `capabilityContract` or on any vendor/slot identity.** No `if (slotKey === 'notifications.email.smtp')`, no switch on `capabilityContract`, anywhere in this workspace's frontend code — every rendering decision (which fields, what type each one is, what status badge to show) comes from the API response's declared metadata, exactly mirroring `ProviderManager`'s own backend discipline ("No vendor name ever appears in a switch/match/if-chain here or anywhere else in this class"). This is what keeps adding a fifth, sixth, tenth provider a pure registration act instead of a growing pile of frontend conditionals.
- **Test Connection never persists.** The flow is Edit → Test → (result shown inline) → Save, never Edit → Save → Test. The "Test Connection" affordance (§27.7) sends the form's *currently-typed, unsaved* field values to a dedicated test endpoint and shows the result without writing anything to the Vault — a manager can try a value, see it fail, and correct it before ever committing a bad credential. This requires a backend-side capability beyond today's `HealthCheckable::healthCheck()` (which only ever reads the already-persisted credential via the Vault); see §27.13 for the new contract this needs.

Save submits every declared field at once (never a per-field PATCH, matching the Vault's all-or-nothing write contract) and requires the current `expectedVersion` exactly as Configuration's optimistic-locking contract already does.

### 27.6 Permission Model

**Resolves §27.2's second difference explicitly.** Viewing the Overview Grid and any provider slot's card/metadata requires no per-slot permission — visible to anyone with general Administration access (mirroring `WorkspaceAccessResolver`'s existing coarse nav-gating philosophy, §26.6's own precedent). Editing a slot's credentials requires exactly the permission declared on that slot's `ProviderSlotDefinition.requiredPermissionToEdit` (e.g. `notifications.manage-email-provider`) — checked by `ProviderCredentialVault::assertCanEdit()`, unmodified, the same "the write endpoint is the real gate, the UI flag is just an accurate preview of it" discipline §26.6 established for Configuration, including the identical `is_super_admin` asymmetry: the view-level bypass (Overview Grid, card visibility) applies to Super Admin per the "general Administration access" rule above, but the edit-level check does not bypass — `canEdit` never promises more than a subsequent write would actually allow.

### 27.7 Configuration Philosophy

Same standing rule as §26.7: real resolved state or an explicit not-connected state, never a plausible-looking mock. A slot with no credential configured at any altitude shows `Needs Setup` as a genuine, correct state — not an error, not hidden. Health-check results are read directly from `HealthCheckRunner`'s existing 60-second cache; the Overview Grid displays whatever that cache currently holds (showing `Checking…`, §27.4, while that initial fetch is in flight) rather than forcing a live check on every page load. The credential form's "Test Connection" affordance is a genuinely different operation from the Overview Grid's cached badge — it tests the *unsaved, currently-typed* values (§27.5's Edit→Test→Save rule), never the persisted credential, and never writes anything regardless of the result — the one interaction this workspace has that Configuration Platform's design didn't need.

### 27.8 Reusable Layout Patterns

The Overview Grid Pattern (§26.16) gains its second real consumer here, confirming it as a genuine cross-workspace pattern rather than a one-off. `WorkspaceHeader`, `StickyActionBar`, `Breadcrumb`, and the risk-tiered approval-confirmation taxonomy (reused for `approval_required` slots exactly as Configuration Platform reuses it) all carry over unchanged.

### 27.9 Empty States

Three conditions, mirroring §26.9's taxonomy one level flatter (no per-category empty state exists here since there's no rail): zero provider slots registered system-wide (System Initialization, unlikely in practice since four slots are already registered, but the shell must still render correctly at zero per the Registration Principle, §27.12); a slot with `Needs Setup` status (a real, non-error state, per §27.7); no Administration access at all (workspace absent from the Sidebar entirely, per `WorkspaceAccessResolver`).

### 27.10 Responsive Behavior

Identical to §26.10/§26.16 — the Overview Grid's 6/4/2/1-column breakpoints are reused verbatim. The credential form (replacing the two-pane rail+detail on mobile) is a single-column form at every breakpoint, since there was never a rail to collapse.

### 27.11 Accessibility

Same conventions as §26.11 — `StickyActionBar`'s Save button keeps a deliberate tab order; write-only credential inputs get real `autocomplete="new-password"`-style hints where the field name implies a secret, so password managers don't attempt to fill them with an unrelated stored credential.

### 27.12 Registration Principle

Unchanged from §26.12, applied to this child specifically: Provider Registry registers into the same `WorkspaceDefinition` registry as every other workspace, under the same Administration `group`. The Administration shell's own "correct with zero, one, or all nine children" guarantee is what makes adding this second child a pure registration act, not a shell change.

### 27.13 Implementation Plan

Mirrors §26.13's own two-phase split, for the identical reason it existed there: the backend (`ProviderManager`/`ProviderCredentialVault`/`HealthCheckRunner`) already exists and is tested, so most of the capability is already there — but pre-freeze review (§27.4/§27.5) surfaced two real, narrow backend contract changes that Phase F-B must make, unlike Configuration Platform's Phase E-B, which needed none:

1. **`ProviderSlotDefinition.credentialFields` gains a type per field.** Today it's `string[]` (Phase 2 scaffold). Becomes an array of `{ name: string, type: 'text' | 'password' | 'secret' }`. Touches: the VO itself, `ProviderRegistry::sync()`'s validation (`assertCredentialFieldsDeclared`), `ProviderCredentialVault::assertCredentialShape()` (extracts names from the richer shape), `ProviderRegistration.credential_fields`'s stored JSON shape (no migration needed, still an `array` cast), and all four existing Providers' `providerSlots()` declarations (`SmtpEmailProvider`, `GoogleOAuthProvider`, `FirebasePushProvider`, `R2StorageProvider`) — each field gets its real type assigned once, by the module that actually knows what it is.
2. **A new contract for testing unsaved credentials.** `HealthCheckable::healthCheck()` only ever reads the already-persisted credential via the Vault — it has no way to test a value the form-filler hasn't saved yet. A new sibling interface (e.g. `TestsCredentials` with `testCredentials(array $credentials): bool`) lets a Provider validate a given, in-memory credential set without touching the Vault at all, satisfying §27.5's Edit→Test→Save rule. Optional, exactly like `HealthCheckable` itself — a Provider with no meaningful pre-save test simply doesn't implement it, and the form's Test Connection button is absent rather than fake.

- **Phase F-A (frontend infrastructure)**: the Overview Grid reused as-is against a temporary fixture provider (fixture slots exercising `Ready`/`Needs Setup`/`Error`/`Disabled`/`Checking…`, with fixture `credentialFields` already carrying real `type` values so the field renderer is built against the real contract from day one, not retrofitted in F-B), `ProviderCredentialForm`'s type-driven field renderer, the Test Connection flow (against the fixture, since the real `TestsCredentials` contract lands in F-B), permission-aware rendering per §27.6, responsive/accessibility verification — the same fixture-then-revert discipline as every prior phase.
- **Phase F-B (Provider Registry integration)**: the two backend contract changes above, a thin adapter-layer REST API exposing `ProviderManager`/`ProviderCredentialVault`/`HealthCheckRunner`/the new test-credentials path (no unrelated business-logic changes, mirroring §26.13's own API stability principle), `@alphaschool/contracts` gains a `providers` feature folder alongside its existing `settings` one, and the real four already-registered slots become the real, non-fixture proof data.

### 27.14 Status

Frozen design (2026-07-20), revised once during pre-freeze review the same day: explicit backend-declared field types replacing a name-based heuristic (§27.4/§27.5), Test Connection's never-persists / Edit→Test→Save sequencing (§27.5/§27.7), a client-only `Checking…` transient status (§27.4), and a binding rule against the UI ever branching on capability contracts or vendor identity (§27.5). All four incorporated before this freeze, not deferred.

**Phase F-A COMPLETE (2026-07-20).** `admin/src/platform/administration/overview-grid.tsx` is the Overview Grid Pattern promoted to a shared component (its second real consumer, confirming §26.16's own claim it would generalize) -- Configuration Platform's usage was updated to the same import, its own workspace-scoped copy deleted, zero behavior change. `admin/src/platform/administration/provider-registry-provider.ts` is the data contract (`ProviderSlot`/`ProviderSlotDetail`/`ProviderCredentialFieldDefinition` with an explicit `type`/`testCredentials`/`writeCredentials`), and `admin/src/workspaces/administration-provider-registry/` holds the real page templates: `provider-registry-page.tsx` (Overview Grid → direct form, §27.3), `provider-credential-field.tsx` (type-driven rendering, never a name heuristic), `provider-credential-form.tsx` (the Edit→Test→Save flow, §27.5/§27.7), `register.ts` (registers the workspace shell only -- no real provider wired yet, mirroring Phase E-A's own split from E-B exactly). Verified live against a temporary fixture (five slots exercising all five statuses including the transient `checking`→`ready` transition; a `canEdit: false` slot proving the view-only path with no Test/Save controls; a real Test→Save round trip proving Test never persists, confirmed by the pending form state surviving a successful test unchanged), RTL/Arabic, dark mode, and mobile -- all fully reverted before commit (zero `git diff` on the fixture wiring, confirmed). `tsc -b` clean, `oxlint` clean (same pre-existing warnings, none new). Phase F-B (the real backend adapter, plus the two new backend contracts §27.13 specified) remains, its own separately-scoped task.

**Phase F-B COMPLETE (2026-07-21).** Both backend contract changes from §27.13 are live: `ProviderCredentialFieldDefinition` (`{name, type}`, `TYPE_TEXT`/`TYPE_PASSWORD`/`TYPE_SECRET`) replaces the plain `string[]` shape everywhere it flowed -- `ProviderSlotDefinition`, `ProviderRegistry::sync()`'s validation, `ProviderCredentialVault::assertCredentialShape()`, and all four existing Providers' declarations (`SmtpEmailProvider`, `GoogleOAuthProvider`, `FirebasePushProvider`, `R2StorageProvider`), each now assigning its own fields' real types. `TestsCredentials` (`testCredentials(array $credentials): bool`), a `HealthCheckable` sibling, is implemented by all four Providers, satisfying §27.5's Edit→Test→Save rule without touching the Vault. A new `ProviderRegistryController` exposes the adapter REST API (`GET /administration/providers`, `GET /administration/providers/{slotKey}`, `POST /administration/providers/{slotKey}/test`, `PATCH /administration/providers/{slotKey}`), and `@alphaschool/contracts` gained a `providers` feature folder mirroring `settings`'s own shape, including a `TestProviderCredentialsResponseSchema` deliberately shaped as just `{ ok: boolean }` -- `TestsCredentials` returns a plain bool, mirroring `HealthCheckable`'s own conservative, message-less result, so the frontend never invents a diagnostic string the backend didn't send.

§27.6's permission model needed one real addition surfaced mid-implementation, not present in the original design: no per-slot *view* permission existed anywhere in `WorkspaceAccessResolver` to gate the workspace itself on (only per-slot *edit* permissions did). Resolved by explicit product decision: a dedicated `administration.providers.view` permission (new `administration` permission group) gates the workspace's visibility in the Sidebar, independent of and never inferred from the union of per-slot edit permissions -- `administration.providers.view` → can see and open Provider Registry; a provider-specific edit permission → can modify that one provider's credentials. `WorkspaceAccessResolver` now includes `provider-registry` in its resolved workspace list only when the acting user holds that permission (or `is_super_admin`, matching the existing view-gating bypass -- edit-gating, unchanged, never bypasses even for `is_super_admin`, since `ProviderCredentialVault::assertCanEdit()` is untouched).

`register.ts` now wires `realProviderRegistryDataProvider` permanently (mirroring E-A→E-B's own split), and the F-A fixture files are deleted, not archived. Verified: 11 new Pest feature tests in `ProviderRegistryControllerTest.php` cover the full slots/detail/test/write/permission/version-conflict matrix end-to-end over real HTTP, including a dedicated test proving Test Connection never persists -- fetch, test, fetch again and assert zero change, save, fetch again and assert the change is now present, so the non-persistence claim is proven by database state, not by UI action sequencing alone. Full backend suite: 354 passing (12 pre-existing, separately-tracked failures unrelated to this work, unchanged -- **superseded, see the correction note below**). Live browser verification against the real Docker backend (not a fixture): all four real slots (`media.storage.r2`, `notifications.email.smtp`, `identity.federation.google-oauth`, `notifications.push.firebase`) list correctly with real `owningModule`/status data; the R2 slot's detail view renders its four real fields with the real backend-declared types (`key`/`secret` masked, `region`/`endpoint` plain text) and correctly disables all of them with the view-only note for a user with `administration.providers.view` but no edit permission on that slot; RTL/Arabic and dark mode both verified live against the real adapter; network and console clean (zero new errors). Edit/Test/Save/permission/version-conflict mechanics for a non-super-admin, edit-permitted user rely on the 11 Pest tests above rather than live browser verification, for the same documented reason as Phase E-B: Spatie Permission's Teams `setPermissionsTeamId()` only resolves correctly within one PHP process, never across real HTTP requests, so a genuine multi-permission browser scenario cannot be constructed against the real backend.

**Notable operational finding, unrelated to this design or its implementation:** during this phase's live-verification setup, the database was found completely empty (zero rows in every table, including `users`, `organizations`, `provider_registrations`) despite an intact, fully-migrated schema. No root cause was identified -- container recreation, config-caching, and a wrong-database mixup were each checked and ruled out. State was restored via `db:seed` plus the existing `administration:sync-settings`/`administration:sync-providers` commands before verification continued. This is flagged here for visibility since it was discovered mid-phase and has not yet been separately investigated or resolved.

**Correction (2026-07-21):** the finding above and the "12 pre-existing failures" a few sentences earlier were the same root cause, not two separate findings, and it has since been found, reproduced live, and fixed -- `php artisan test` was silently connecting to the real MariaDB dev database instead of the isolated in-memory SQLite `phpunit.xml` declares, so every test run dropped and recreated every table (`RefreshDatabase`'s `migrate:fresh`) in the real dev database; the MariaDB-specific strict-mode column enforcement behind the 12 failures doesn't apply once tests correctly run against SQLite. Both statements above were accurate reports of what was known at the time of writing, not errors -- this note supersedes their conclusions, not their honesty. With the fix in place the full suite passes 367/367. Full RCA, live reproduction, and fix: `docs/developer/rca-2026-07-21-test-database-wipe.md`.

---

## 28. Academic Reference Workspace — Entity List, Detail & Form Pattern (Frozen 2026-07-30)

Reached through a dedicated Architecture & UX Pass (`docs/IMPLEMENTATION_PLAYBOOK.md` Frontend Track F2, UI Sprint 1) grounded in direct inspection of the existing `admin/src/platform/` code and the real backend schema (`AcademicYear`/`Term`/`GradeLevel`/`Subject`/`Section` migrations) rather than assumption, then reviewed and closed with no substantive changes beyond two additions (§28.9's Capabilities Metadata, §28.7's Delete rule). This is the reference implementation for **entity-CRUD workspaces** specifically — the shape §26.1 explicitly deferred ("a different shape... need their own reference proof separately") when Administration Workspace proved the configuration-oriented shape instead. First consumer: the Academic workspace's five Master Data entities (`AcademicYear`, `Term`, `GradeLevel`, `Subject`, `Section`).

### 28.1 Purpose and Scope

Realizes §7's already-frozen List Template and Form Template — named as high-level shapes since the original design pass, never before turned into a concrete, implementable pattern — and adds a third sibling template, **Detail**, that §7 didn't originally name (closing §M8). Scope is the *pattern* itself: List/Detail/Form page shells, their shared components, and the declarative metadata contract that lets one pattern serve five structurally-different entities without per-entity branching. Binding Timetables/Attendance/Grades UI, or any `HasTemporalAssignment`-backed entity, is explicitly out of scope (§28.8 rule 7).

### 28.2 Information Architecture and Navigation

**Flat Tab Switcher, not Overview Grid.** The `Academic` workspace (§8.3, a flat top-level entry) renders a horizontal tab row directly under its `WorkspaceHeader` — Academic Years | Terms | Grade Levels | Subjects | Sections — each tab rendering that entity's List page. **Considered and rejected: reusing the Overview Grid Pattern (§26.16) as the workspace landing page**, on two grounds: (1) Overview Grid's status badge is semantically committed to configuration health (`Ready`/`Needs Setup`/`Error`/`Disabled`) with no honest equivalent for a plain Master Data catalog; (2) Overview Grid's documented intended use (§26.16's own table) is a *grid of structurally distinct capability areas* a user visits occasionally — Academic's five entities are structurally identical (same pattern) and visited constantly, which a tab switcher represents more honestly and with less navigation depth.

The tab switcher is a workspace-internal concern, registered in a local config array owned by the Academic workspace — **not** a new global extension point, and not the same mechanism as §8.2's `WorkspaceDefinition.group` (which clusters distinct top-level workspaces under Administration; this clusters same-shape entity lists within one workspace). Breadcrumb: Home → Academic → [Entity plural] → [Create/Edit/Detail], via the existing multi-level `Breadcrumb`, unchanged.

### 28.3 Page Templates — Three Independent Siblings

**List → Detail → Form are three separate patterns, not one component with modes.** Navigation:

```
List  →  [row action]  →  Detail (read-only)  →  [Edit]  →  Form (edit)
List  →  [New]  ─────────────────────────────────────────→  Form (create)
```

`EntityDetailPage` and `EntityFormPage` are independent patterns, not a wrapper/variant pair — Detail starts minimal (breadcrumb + read-only field values + Edit action) but is the designated future home for related-record panels, timelines, and statistics (§28.10) **without ever collapsing into a form**; keeping them separate now avoids a later component split under pressure. This closes §M8 (no read-only view distinct from edit existed anywhere in Old Admin) — a user holding `view` but not `edit` lands on a real Detail page with no Edit action rendered, never a disabled form.

**Create/Edit is always a full page**, per §7.2's already-frozen Form Template — deliberately including entities as small as `AcademicYear` (4 fields). Not reopened for per-entity exceptions: a consistent pattern across all five entities was judged more valuable than saving one click on the smallest ones, and a size-based exception today invites unprincipled exceptions for every future entity someone judges "small enough."

### 28.4 Entity List Pattern

- **Toolbar:** `WorkspaceHeader` (title + primary "New [Entity]" action) + a search/filter row beneath it.
- **Search:** a new list-scoped `SearchInput` (distinct from the global `SearchBar`), 350ms client debounce before writing to the URL querystring, server does the actual filtering, page resets to 1 on change.
- **Filters:** one baseline **Status** filter, whose shape is declared per entity (§28.8 rule 5) — a boolean `Active/Inactive/All` control for `GradeLevel`/`Subject`/`Section` (real `is_active` columns, confirmed by migration), or a three-value `Upcoming/Active/Closed/All` control for `AcademicYear`/`Term` (real `status` enum, confirmed by migration — corrects an initial draft assumption that all five entities shared one boolean shape). A **"N filters active" indicator with one-click Clear All** is new, closing §1.2's documented Old Admin gap (no filter-count affordance, no clear-all).
- **Table behavior:** the existing `DataTable`/`useServerDataTable` (`platform/data-table/`) is reused verbatim — server-side sort/pagination against a Laravel-shaped paginated resource, already generic, previously exercised only by the dev harness, never by a real workspace until this one. A trailing "…" `DropdownMenu` row-action column (reusing the existing primitive) offers View / Edit / **Deactivate or Close** (§28.7 — never Delete).
- **Bulk actions / row selection:** explicitly out of Sprint 1's baseline. `DataTable`'s underlying TanStack Table already supports `enableRowSelection` if a real future need surfaces (per-entity, via §28.8's capabilities metadata) — not built speculatively now.
- **Pagination:** upgraded from the current prev/next-only control to numbered-with-ellipsis, **in scope for Sprint 1** — a Foundation-level fix (§6.3's long-documented gap), not an Academic-specific feature, correctly landing before dozens of future screens start depending on the weaker version.
- **Loading:** first load keeps the existing skeleton-row treatment; a refetch (filter/search/page change) dims existing content (`opacity-70`) rather than blanking or re-spinning — fixing a real, confirmed gap in `data-table.tsx` (it does not currently distinguish the two cases) against the already-frozen rule in §9 ("List pages never blank-and-reflow during a background refetch").
- **Empty states, two distinct conditions:** zero rows because none exist yet ("No {Entities} yet" + a prominent create CTA) vs. zero rows because the current filter/search matched nothing ("No results match your filters" + Clear Filters) — different copy, different fix, matching §26.9's discipline of never collapsing distinct empty conditions into one generic message.
- **Error state:** existing inline error text, extended with a **Retry** action — confirmed absent from `data-table.tsx` today, a small, disclosed addition to the same file.

### 28.5 Entity Detail Pattern

`EntityDetailPage`: breadcrumb → `WorkspaceHeader` (entity's display name/code as title) → read-only field values (same field set the Form declares, §28.8 rule 1, rendered as plain text/labels, not disabled inputs) → an Edit action, rendered only when `capabilities.canEdit` (§28.9) is true for the current user. No related-record panels, timelines, or statistics are built in Sprint 1 itself — the shell is guaranteed for all five entities now; per-entity content (e.g. an `AcademicYear` Detail page eventually listing its `Term`s) is added only once that consuming entity is itself built, per the same "promotion, not prediction" discipline already applied throughout this codebase (Blueprint Addendum B1) — not spec'd speculatively here.

### 28.6 Entity Form Pattern

- **Layout:** a single card-sectioned form (§7.2), sufficient for all five entities today (the largest, `Section`, has six fields including three FK selects); the shape already supports N sections with no pattern change if a future entity needs them.
- **Bilingual fields:** `BilingualNameField` (existing, `platform/forms/`) reused verbatim wherever an entity declares `bilingual: true` (`AcademicYear`, `Term`, `GradeLevel`, `Subject` — all confirmed `name_en`/`name_ar` pairs by migration); `Section.name` is a single plain `TextField`, per its own already-decided, non-bilingual design (Phase 5 Sprint A: "expected values like 'A'/'B'/'1', not content needing translation") — the pattern follows this per-entity declaration, it does not impose bilinguality universally.
- **Validation:** a Zod schema per entity (per the standing ADR-0023 Zod-First Contracts convention) + React Hook Form, inline `fieldState.error` display via the existing `TextField`/`SelectField`, and the existing `mapServerErrors` for 422s — unmodified.
- **Consistency Invariants stay entirely server-side.** A `SubjectOffering`-style three-way mismatch (or any future cross-entity invariant) surfaces to the user as an ordinary 422 field/form-level error via the existing `mapServerErrors` — **no invariant logic is duplicated in React under any circumstance**, matching the backend's own discipline of enforcing every invariant exactly once, in `booted()`.
- **Save/Cancel:** the existing `StickyActionBar` (Cancel → back to List; Save → submit), reused verbatim per §10/§26.5's precedent.
- **Dirty-state handling — closes §M3.** Direct inspection of `platform/forms/` and `platform/routing/` confirmed no unsaved-changes guard exists anywhere in the codebase today — not even in Configuration Platform or Provider Registry, whose single-page save flows never previously created this risk the way List↔Form navigation now does. A new, generic `useUnsavedChangesGuard` (in `platform/forms/`, wired to `formState.isDirty`) is Sprint 1's first real implementation of the rule §9/§M3 specified since the original design pass.
- **Success/error feedback — closes §M10.** No shared mutation wrapper guaranteeing a success toast exists today. A new generic `useEntityMutation` (wrapping React Query's `useMutation`) always fires an explicit success/error toast, removing the possibility of a future page author forgetting it.

### 28.7 Domain Rule — Reference Master Data Never Exposes Delete

**Reference Master Data entities never expose a Delete action in the UI, under any permission or role — this is a domain invariant, not a UI styling choice.** Every entity in this pattern's scope is already documented at the schema level as "reference/structural entity — never hard-deleted" (`academic_years`, `grade_levels`, `sections` migrations all carry this comment verbatim; the same convention applies to `subjects`/`terms`). The UI pattern's row-action menu offers only **View / Edit / Deactivate** (boolean-`status` entities) or **View / Edit / Close** (lifecycle-`status` entities, §28.8 rule 6) — no Delete affordance exists to gate behind a permission, because the backend has no delete path to call. Any future entity added to this pattern must declare which of the two applies (§28.8 rule 6); a genuinely deletable entity is a different category (transactional data, not Reference Master Data) and does not use this pattern's row-action set at all without a separate, explicit design decision.

### 28.8 Declarative Entity Metadata — Extension Rules

The pattern extends per entity through declared metadata consumed by shared components, never through per-entity branches (`if (entity === ...)`) inside them — the same discipline §27.5 already bound Provider Registry to ("the UI never branches on vendor identity, only on backend-declared metadata"), applied here to entity identity instead.

1. **Fields.** Each entity declares a field-config array (name, type, component) driving the shared `EntityFormPage`'s render — the shell contains no per-entity field logic.
2. **Columns.** Each entity declares `columns: ColumnDef<T>[]`, the contract `useServerDataTable` already exposes generically — proven, zero shell changes needed.
3. **Filters.** The baseline Status filter is mandatory in the shape rule 5 declares; entity-specific filters (e.g. scoping `Term` by `AcademicYear`) attach via an optional `extraFilters` slot, additive to the toolbar, never a structural rewrite.
4. **Bilinguality.** Each entity declares `bilingual: true | false` for its name field — `Section` is the one `false` today; the rest are `true`.
5. **Status shape.** Each entity declares `statusType: 'boolean' | 'lifecycle3'`, determining the List filter's options and the row action's label (Deactivate vs. Close) — never Delete (§28.7).
6. **Invariants.** Always server-side, surfaced as standard 422 errors (§28.6) — no client-side exception to this rule.
7. **Pattern boundary.** Any entity with a real `HasTemporalAssignment`-backed lifecycle (effective dates, overlap — e.g. Teacher Assignment, Homeroom Assignment) is explicitly outside this pattern, routed to UI Sprint 2 (Temporal Assignment Workspace) instead. Test: does the entity have effective dates/overlap semantics? Yes → Sprint 2; no (a plain `is_active`/`status` field only) → this pattern.
8. **Detail content.** The shared Detail shell (§28.5) is guaranteed for every entity; entity-specific panels/related-records content is added only when that consuming entity is actually built, never speculatively.
9. **Capabilities.** Each entity/workspace declares a `capabilities` object — `{ canCreate, canEdit, canDeactivate, hasDetail, hasBulkActions }` — read by the shared List/Detail/Form shells to decide which actions/affordances render, replacing what would otherwise become scattered `if (entity === ...)` conditionals in the UI as more entities join the pattern. All five Sprint 1 entities declare `hasBulkActions: false` today (rule 3's baseline exclusion), `canCreate`/`canEdit`/`canDeactivate: true`, `hasDetail: true`.

### 28.9 Shared Component Inventory

| Component | Status | Note |
|---|---|---|
| `DataTable` + `useServerDataTable` | **Existing** (`platform/data-table/`) | Generic, previously unconsumed by any real workspace — first real consumer here |
| `TextField` / `SelectField` / `DateField` / `BilingualNameField` | **Existing** (`platform/forms/`) | RHF + Zod, ready |
| `mapServerErrors` | **Existing** | Generic, Laravel 422 shape |
| `StickyActionBar` | **Existing** (`platform/components/ui/`) | Frozen §10/§26.5 |
| `useConfirm` / `ConfirmDialog` | **Existing** (`platform/modals/`) | Reused for Deactivate/Close confirmation |
| `Breadcrumb`, `WorkspaceHeader` | **Existing** (`platform/shell/`) | Ready |
| `Badge`, `Table`, `Skeleton`, `Dialog`, `DropdownMenu`, `Select`, `Input`, `Label` | **Existing** (primitives) | Ready |
| Tab Switcher (Academic's 5-entity internal nav) | **New** | Small, workspace-scoped, no precedent to reuse |
| `SearchInput` (list-scoped, debounced) | **New** | Distinct from the global `SearchBar` |
| Filter toolbar + "N active" chip | **New** | No filter mechanism exists in `platform/` yet |
| Numbered Pagination | **Extension of existing `DataTable`** | Upgrades, does not replace, the current prev/next control |
| `DataTable` Retry + dim-on-refetch | **Extension of existing `data-table.tsx`** | Small, in-place fix |
| `useUnsavedChangesGuard` | **New** | Closes §M3 |
| `useEntityMutation` | **New** | Closes §M10 |
| `EntityDetailPage` | **New — independent pattern**, not a wrapper around `EntityFormPage` | §28.3 |
| `EntityFormPage` | **New — independent pattern** | §28.3 |
| Status-aware row action (Deactivate/Close) | **New**, small | Consumes the existing `useConfirm`, no new dialog |

No component requires a rebuild from scratch — every foundational primitive (Table/Form/Modal/Header/Breadcrumb) already exists and is generic; every new item is additive.

### 28.10 Reusable Layout Patterns

`StickyActionBar`, `Breadcrumb`, `WorkspaceHeader`, and `useConfirm` all carry over unchanged from §26/§27's own precedent — this pattern's contribution is `DataTable`'s first real wiring, plus the new List/Detail/Form shells and the declarative metadata contract (§28.8), all deliberately built reusable by every future entity-CRUD workspace (Students, HR, Library, Finance), not scoped to Academic specifically.

### 28.11 Permission Model

Each entity/workspace's `capabilities` (§28.8 rule 9) reflects, but never grants, real server-side permission checks — the same "the write endpoint is the real gate, the UI flag is just an accurate preview of it" discipline §26.6/§27.6 already established. `canEdit: true` never promises more than a subsequent write would actually allow. A user with view-only access sees List and Detail with no Edit/Deactivate/New affordances rendered; List rows the user has no view permission for are simply absent, not shown-then-blocked.

### 28.12 Empty States

Two conditions (List, §28.4) — no third "not connected" state exists here since these are always-real Master Data reads, not provider-backed configuration (§26.9/§27.9's third condition doesn't apply to this pattern).

### 28.13 Responsive Behavior

Desktop table behavior is unchanged from `DataTable`'s current baseline (`overflow-x-auto` horizontal scroll) — a full card/list mobile fallback (§3 improvement 9's stated goal) is a **disclosed, deliberate deferral**, not built in Sprint 1, tracked as a known Foundation-level enhancement for a future pass once a real mobile usage pattern justifies the cost, the same "promotion, not prediction" judgment applied throughout this document. Detail/Form pages (single-column already) require no responsive change.

### 28.14 Accessibility

Tab Switcher is a real nav landmark (`aria-label`, matching `Breadcrumb`/`SideNav`'s existing convention); the row-action "…" trigger gets a real `aria-label` (icon-only, per §9's blanket rule); `StickyActionBar`'s Save button keeps a deliberate tab order on Form pages, per §26.11's precedent.

### 28.15 Registration Principle

The Tab Switcher's five entities are registered in a local, Academic-workspace-scoped config array — explicitly **not** the global `WorkspaceDefinition` registry (§8.2), which governs top-level workspace nav, a different concern. A future entity-CRUD workspace (Students, HR) implementing this same pattern registers its own local tab/entity config independently; nothing here creates a new shared cross-workspace registry.

### 28.16 Implementation Plan

Mirrors §26.13/§27.13's own two-phase discipline:

- **UI Sprint 1-A (frontend infrastructure):** `EntityListPage`/`EntityDetailPage`/`EntityFormPage` shells, the declarative metadata contract (§28.8), `useUnsavedChangesGuard`, `useEntityMutation`, `SearchInput`, filter toolbar, numbered Pagination, `DataTable` retry/dim-on-refetch — built and verified against a temporary, fully-reverted fixture (one or two fixture entities), the same discipline as every prior phase (Phase B/D/E-A/F-A).
- **UI Sprint 1-B (real Academic entities):** binding the five real entities (`AcademicYear`/`Term`/`GradeLevel`/`Subject`/`Section`) via each one's declared metadata, plus the thin adapter-layer REST endpoints each needs (mirroring E-B/F-B's own "expose existing services verbatim" principle) — sequenced after 1-A, per the standing rule that backend-facing capability is proven against a fixture before real data flow is wired.

### 28.17 Status

**Frozen design (2026-07-30).** Architecture & UX Pass reviewed with no substantive changes beyond this freeze's own two additions (§28.9's Capabilities Metadata field, §28.7's Delete rule, both incorporated before freeze, not deferred) — mirroring §27.14's own same-day pre-freeze revision precedent.

**Phase 1-A (frontend infrastructure) COMPLETE (2026-07-30).** All of §28.4/§28.5/§28.6's shells and the §28.8 declarative metadata contract are real, shipped code in `admin/src/platform/`: `entity-workspace/` (`entity-metadata.ts`'s types, `entity-list-page.tsx`, `entity-detail-page.tsx`, `entity-form-page.tsx`, `entity-field-value.tsx`, `entity-workspace-shell.tsx` — the Flat Tab Switcher — `entity-route.ts`, `locales/`), `data-table/search-input.tsx`, `data-table/filter-toolbar.tsx`, `data-table/pagination.tsx` (numbered-with-ellipsis, replacing the prev/next-only control), `forms/use-unsaved-changes-guard.ts` (closes §M3), `forms/use-entity-mutation.ts` (closes §M10), `toasts/` (a new toast primitive — `toast-store.ts`/`toast-host.tsx`, mirroring `modal-store.ts`/`modal-host.tsx`'s own imperative-stack shape; no toast mechanism of any kind existed anywhere in this codebase before this phase, confirmed by inspection), and `components/ui/tabs.tsx` (a new Radix-backed primitive, §11's "don't hand-roll when Radix is available" rule). `data-table.tsx` was extended in place, not replaced, with the dim-on-refetch/Retry behavior §28.4 specified.

**A real, load-bearing routing gap was found and surfaced before implementation, per the project's standing "surface before implementing" discipline** (Provider Registry's own Phase F-B precedent, §27.14): `router.tsx` had exactly one route per workspace (`/w/$workspaceKey`), and Configuration Platform/Provider Registry's own List↔Detail navigation both use local `useState`, never the URL — confirmed by direct inspection, not assumed — which contradicts §7.1's own already-frozen praise for URL-driven, deep-linkable list state. Resolved by explicit user decision: extend the router (recommended option) rather than repeat the local-state shortcut a third time. `router.tsx` now nests `workspaceIndexRoute` (`/`) and `workspaceSplatRoute` (`$`) under a shared `workspaceRoute` parent — a flat sibling-route version was tried first and produced a real, reproduced TanStack Router console warning (ambiguous path generation between the two full paths); the nested index+splat shape is TanStack Router's own documented pattern for this exact case and has no such ambiguity. A new `workspace-sub-path.ts` context threads the splat value down to `WorkspaceRoutePage` additively — no existing workspace's own code changed, and no workspace that never calls `useWorkspaceSubPath()` is affected.

**Three real bugs found and fixed during live verification** (Docker backend, real login as `testuser`, a temporary two-entity fixture proving both status shapes — `bilingual: true` + `statusType: 'boolean'` and `bilingual: false` + `statusType: 'lifecycle3'` — fully reverted before this entry was written, confirmed via `git diff` showing zero trace on `App.tsx`/`use-visible-workspaces.ts`):

1. The fixture's own mock data provider mutated cached row objects in place (`row.is_active = ...`) instead of replacing them immutably. Since React Query's structural-sharing comparison saw the "new" fetch result as identical to the already-mutated cached object, `invalidateQueries` silently produced no re-render even though the underlying data had genuinely changed — a Deactivate/Activate/Close action's own row updated internally but never visibly. Real backends never have this failure mode (every HTTP response is a fresh JSON object), but the fixture itself was fixed to replace array entries immutably rather than left as a known-wrong shortcut, since the point of live verification is to prove the pattern, not paper over a shortcut in the harness proving it.
2. `useUnsavedChangesGuard`'s `shouldBlockFn` had its boolean sense inverted — `useBlocker` treats `true` as "block/stay," but `useConfirm` resolves `true` when the user clicks the confirm ("Discard") button, i.e. when they want to leave. Found live: clicking "Discard" silently failed to navigate. Fixed by negating the resolved value.
3. A successful Save still popped the "Discard unsaved changes?" prompt on its own resulting navigation. Root cause: `onSuccess` called `reset(...)` (clearing `isDirty`) and `onSaved(row)` → `navigate(...)` synchronously in the same callback tick; `useBlocker`'s re-registration effect (which would have picked up the now-false `isDirty`) had not yet committed by the time `navigate()` ran, so the *stale* blocker intercepted its own success path. Fixed by routing the saved row through a state variable and firing `onSaved` from a separate `useEffect`, guaranteeing the guard's re-registration commits first.

A fourth, smaller gap was also found and fixed: `EntityFormPage`'s breadcrumb didn't pass an `href` for its intermediate segment, so "Catalog Items" rendered as inert text instead of a link on Create/Edit pages — fixed by adding a `listHref` prop, threaded from `EntityWorkspaceShell`.

Full verification: `tsc -b` clean, `oxlint` clean (same two pre-existing-pattern warnings, none new), `vitest run` 4/4 passing (no regressions; no new automated tests were added for the entity-workspace pattern itself in this phase — verified live instead, matching Phase E-A/F-A's own precedent). Live browser, real Docker backend, real login: List (search debounce, status filter for both shapes, "N filters active" + Clear All, numbered pagination, both empty-state conditions) → Detail (read-only fields, permission-aware Edit visibility) → Form (Create and Edit, both navigating correctly post-save) all exercised end-to-end for both fixture entities; row-level Deactivate/Activate (boolean) and Close (lifecycle3, correctly absent once already closed) confirmed, including their confirm dialogs and success toasts; the unsaved-changes guard confirmed blocking in both directions (Keep Editing stays, Discard leaves) with no false-positive on save; RTL (real `i18next.changeLanguage`, correct Arabic strings throughout including interpolated entity names) and dark mode (`data-theme="dark"`) both confirmed with zero console errors.

**A subsequent independent read-only repository review — fresh reads and fresh command re-runs, not a review of the implementation summary — found no layering, duplication, router, guard-logic, or §28-compliance issues.** Specifically verified independently: `entity-workspace/` contains zero Academic-specific identifiers outside comments and zero imports from `@/workspaces/*`/`@/dev/*`; the nested index+splat router structure is real, not just described, and `tsc -b`/`vite build` both re-ran clean; `useUnsavedChangesGuard`'s boolean was independently re-derived from `useBlocker`'s own library source (not merely diffed against the pre-fix version) and confirmed correct; the fixture's absence and the zero-`git diff` claim on `App.tsx`/`use-visible-workspaces.ts` were independently re-verified, not re-quoted; `EntityDataProvider`'s type has no delete method at all (§28.7), and `entity.capabilities.*` is genuinely read for every gated affordance with no `entity.key === ...` branching anywhere (§28.8 rule 9). **Certified APPROVED FOR FINAL CLOSURE** — the same evidentiary bar applied to Sprint 4.2/4.3/4.4/B/C, not a lighter pass because this was frontend work. Two minor, non-blocking findings recorded as new Technical Debt Register entries (`docs/IMPLEMENTATION_PLAYBOOK.md`) rather than fixed inline, since neither affects correctness: `data-table/search-input.tsx`'s debounce duplicates `platform/search/search-bar.tsx`'s near-identical logic (no shared `useDebouncedValue` hook exists to reuse — the same category of gap `search-input.tsx`'s own comment already disclosed); and `platform/i18n/index.ts` statically imports `entity-workspace/locales/*` rather than using its own adjacent `registerWorkspaceTranslations()` extension point, inconsistent with that file's own stated self-description (defensible if `entity-workspace` is platform infrastructure rather than a workspace, but not reconciled explicitly).

**UI Sprint 1-A: COMPLETE AND CLOSED (2026-07-30).**

**UI Sprint 1-B (binding the five real Academic entities) is complete — see §29.**

---

## 29. Entity Workspace Integration — Five Real Academic Entities Bound (UI Sprint 1-B, COMPLETE AND CLOSED 2026-07-30)

**Explicit success criterion, set before implementation began, more important than any individual bug found:** were five structurally different real entities wired into §28's pattern with zero fork or `if (entity === ...)` inside the shared infrastructure — proof the Sprint 1-A freeze generalizes, not just a batch of screens. Verified true, independently, in §29.9 below.

**Integration order (deliberate, not arbitrary):** AcademicYear (bilingual + lifecycle3 — simplest real CRUD proof) → GradeLevel (bilingual + boolean, first boolean-status proof) → Subject (a second boolean-status entity, confirming no further shared-code change was needed) → Term (first real FK — `academic_year_id` — first real `extraFilters` consumer) → Section (non-bilingual + three FKs, the hardest test). Each entity was implemented, verified live against the real Docker backend, and its own backend suite run green before starting the next — never batched.

### 29.1 Backend: One Shared Adapter Controller, Five Thin Subclasses

`app/Modules/Academic/Http/Controllers/Concerns/AcademicMasterDataController.php` (new) generalizes on the backend the exact discipline §28.8 already bound the frontend to: one real implementation of list (search/status/sort/pagination, shaped as `{data, meta}` matching `ServerPage<T>` — Laravel's own raw paginator JSON is flat, a real mismatch found and fixed here), get, create, update, and setStatus (boolean toggle, or `close()` reuse for the lifecycle3 `'closed'` transition — never a raw mass-update standing in for real domain behavior), plus a no-op `applyExtraFilters()` hook a subclass can override. `AcademicYearController`/`GradeLevelController`/`SubjectController`/`TermController`/`SectionController` (all new) are each 40-65 lines declaring only `modelClass()`, `searchableFields()`, `statusField()`/`statusType()`, and `validationRules()` — Term and Section are the only two overriding `applyExtraFilters()` (both filter by `academic_year_id`). §28.7's no-Delete rule is enforced structurally, not by convention: the shared base has no `destroy()` method, so no subclass could accidentally expose one, and no `DELETE` route exists anywhere in the new `academic` route group (`routes/api.php`).

A minimal, read-only `BranchController` (new, `app/Modules/Identity/Http/Controllers/`) was added because Section is the first Academic entity with a real Branch FK and no Branch list endpoint existed anywhere in the API surface — `GET /branches` returns `{id, name_en, name_ar}` for active branches only, gated by the already-seeded `branches.view` permission (no new permission invented). Deliberately not a Branch CRUD workspace — that remains a future Identity-module task, out of scope here.

### 29.2 Frontend: Declarative Metadata, Two Additive Pattern Extensions

Each entity is a plain `EntityDefinition<T>` object under `admin/src/workspaces/academic/entities/` — no entity-aware branching in any shared file. Two genuinely new capabilities were added to the shared pattern, both additive (the three entities that don't need them are structurally unaffected):

- **`'async-select'` field kind** (`entity-metadata.ts`, `platform/forms/async-select-field.tsx`, `entity-field-value.tsx`'s `AsyncSelectFieldValue`) — a `'select'` field whose options come from a live query (`loadOptions`/`queryKey`) rather than a static array declared at module load time. First real consumer: Term's `academic_year_id`; Section uses it three times (`branch_id`/`academic_year_id`/`grade_level_id`). The Detail page resolves the stored ID back to its real label via the same `loadOptions` call, not a raw ID.
- **`extraFilters` slot realized** (§28.8 rule 3, named but unbuilt at freeze time) — `EntityDefinition.extraFilters`, `platform/data-table/extra-filter-control.tsx`, and `EntityListPage`'s own generic `extraFilterValues` state (folded into the query key/params and the "N filters active" count). First real consumer: Term and Section's own "Academic Year" list filter, sharing the exact same `loadAcademicYearOptions()` function their own form fields already use — one real Academic Year fetch, not two parallel implementations.

### 29.3 Three Real Backend Bugs Found and Fixed During Live Verification

1. **Pagination envelope shape mismatch.** `response()->json($paginator)` returns Laravel's raw, flat paginator shape; the frontend's `ServerPage<T>` contract expects `{data, meta: {current_page, last_page, per_page, total}}`. Fixed once, generically, in `AcademicMasterDataController::index()`.
2. **Missing `$attributes` defaults on all five models.** A DB-level `->default(...)` (migration) is never reflected into a freshly `::create()`'d Eloquent instance without an explicit `protected $attributes` default — every existing test created rows through a Factory, which always set the field explicitly, so this was latent until the real, factory-free create path (this Sprint's own controllers) exposed it live: `AcademicYear`/`Term` were missing `status => 'upcoming'`; `GradeLevel`/`Subject`/`Section` were missing `is_active => true`. Fixed on all five models. The three test files whose "creates with default" test only asserted `assertDatabaseHas` (which passes regardless, since the migration's own DB default writes the correct row either way) were corrected to also assert the JSON response body directly (`response->json('status')`/`response->json('is_active')`) — the level at which the bug actually manifested — per this Sprint's own independent review (§29.9).
3. **Unset foreign-key casts.** `Term.academic_year_id` and `Section.{branch_id,academic_year_id,grade_level_id}` were not cast to `'integer'`; an HTML `<select>`'s value is always a string, so a freshly created row round-tripped its FK as `"37"` in JSON, failing the frontend's `z.number()` contract even though the write itself was correct. Fixed by adding explicit `'integer'` casts — confirmed, not assumed, via a dedicated test asserting `response->json('branch_id')` etc. are real integers (`SectionControllerTest.php`).

A fourth, smaller frontend-only gap was found and fixed generically (not per-entity): HTML `<input type="date">` silently rejects a full ISO datetime string (what a real backend returns for a date-cast column) — fixed once in `entity-form-page.tsx`'s `buildDefaultValues` (truncate to `YYYY-MM-DD`) and `entity-field-value.tsx` (Detail's read-only display), covering every entity's own date fields, not just AcademicYear's.

Every `validationRules()` was also audited for real DB unique constraints missing a matching `Rule::unique(...)->ignore($id)` (a gap that would otherwise surface as a raw 500 `QueryException` instead of a clean 422 on update) — found missing on the first pass for GradeLevel/AcademicYear, added correctly (including Term's and Section's own scoped/composite variants matching their real composite unique constraints) before those two entities were considered done.

### 29.4 Live Verification

Docker backend, real login as `testuser`, real dev database (not a fixture) — each entity's full List → Create → Edit → Detail → status-action flow exercised live before moving to the next: AcademicYear (Close, `AcademicYearClosed` event dispatch confirmed via a real Pest `Event::fake()` assertion, idempotent re-close confirmed), GradeLevel and Subject (Deactivate/Activate, boolean filter), Term (async-select populated with real Academic Year rows, composite uniqueness rejection surfaced correctly as an inline field error — confirmed by deliberately hitting it live, not just in Pest — extraFilters genuinely narrowing the list), Section (all three FK dropdowns populated with real data, Detail correctly resolving all three IDs back to real labels, zero errors on the first attempt thanks to the proactive FK-cast fix already being in place). Zero console errors throughout every entity's own pass.

### 29.5 Backend Test Coverage

Five new Pest Feature test files (`tests/Feature/Academic/{AcademicYear,GradeLevel,Subject,Term,Section}ControllerTest.php`), 31 tests total, covering per-entity CRUD, the relevant status-shape branch, permission gating (including `is_super_admin` bypass), each entity's own real validation edge cases (composite uniqueness, FK existence, end-date-after-start-date), and — for Section — a dedicated router-table assertion that no `DELETE` route exists. `BranchController` covered by one test asserting only active branches are returned.

### 29.6 Verification Results

Backend: `php artisan test` **510/510 passing** (31 new), `./vendor/bin/pint --test` clean (417 files), `./vendor/bin/deptrac analyse --no-cache` **0 violations**. Frontend: `tsc -b` clean, `oxlint` clean (same two pre-existing warnings, none new), `vitest run` 4/4 (no new automated tests added for the entity definitions themselves — verified live instead, matching Sprint 1-A's own precedent). `packages/contracts/src/academic/` gained real Zod schemas for all five entities plus a shared `paginatedSchema()` helper and `BranchOptionSchema`/`BranchListResponseSchema` for the lookup endpoint.

### 29.7 Documentation-First Compliance Note

The initial pass through this Sprint implemented and verified all five entities correctly but did not update this document, `docs/IMPLEMENTATION_PLAYBOOK.md`, or `CHANGELOG.md` before requesting review — flagged as a blocking finding by the independent review below (Finding A) per this project's own binding "hand-off-ready documentation" and "documentation-first architecture" rules. Corrected in this same pass, not deferred: this section, the Playbook's Sprint 1 status row, and the CHANGELOG entry were all written before closure.

### 29.8 Independent Read-Only Repository Review

A fresh agent, no prior context, independently verified — not re-quoted — every claim above: grepped all shared frontend (`platform/entity-workspace/`, `platform/data-table/`, the touched `platform/forms/` files) and the shared backend controller for any of the five entity names outside comments (zero hits); read all five backend controllers and all five frontend entity definitions in full, confirming each is genuinely thin/declarative with no duplicated logic; confirmed the `async-select`/`extraFilters` extensions are driven purely by passed-in config, not entity branching, and that the three unaffected entities are structurally untouched; confirmed §28.7's no-Delete rule holds structurally (no `DELETE` route, no `destroy()` method, `EntityDataProvider`'s own type has no delete method); cross-checked all three bug fixes against the real migration files (not just against the fix's own claim) — the `$attributes` defaults, the FK integer casts, and every `Rule::unique(...)->ignore($id)` call, confirming each matches its table's actual DB constraint; re-ran `php artisan test`/`pint`/`deptrac` and `tsc -b`/`oxlint`/`vitest run` fresh, all green; grepped every touched file for leftover debug statements (zero found); confirmed `BranchController` stays a minimal, correctly-scoped lookup (only active branches, only `id`/`name_en`/`name_ar`, no CRUD); and confirmed live row counts against the real dev database (`AcademicYear: 2, GradeLevel: 1, Subject: 1, Term: 3, Section: 1`) rather than trusting the live-verification narrative.

**Two findings, both resolved before this section was finalized:**
- **Finding A (blocking):** this document, the Playbook, and the CHANGELOG had not been updated — resolved by writing this section, §29's own existence being the fix, plus the Playbook/CHANGELOG entries described in §29.7.
- **Finding B (non-blocking):** the three "creates with default" tests asserted only `assertDatabaseHas`, which passes regardless of whether the `$attributes` fix is present (the migration's own DB default writes the row correctly either way) — resolved by adding `response->json(...)` assertions to all three (§29.3 item 2), re-run green (§29.6 above already reflects the corrected suite).

**Certified APPROVED FOR FINAL CLOSURE** on this basis — the same evidentiary bar applied to every prior phase in this project, backend or frontend.

### 29.9 Status

**UI Sprint 1-B: COMPLETE AND CLOSED (2026-07-30).** The success criterion stated at the top of this section held: five structurally different real entities (two status shapes, bilingual and non-bilingual names, zero and one and three real FKs) bound to §28's pattern with zero fork or entity-identity branch anywhere in the shared frontend or backend infrastructure — independently verified, not self-reported. Frontend Track F2's Academic milestones (UI Sprint 1 → 1-A → 1-B) are now fully complete. UI Sprint 2 (Temporal Assignment Workspace) is next, per `docs/IMPLEMENTATION_PLAYBOOK.md`'s Frontend Track F2 roadmap.

---

## 30. Temporal Assignment Workspace — Architecture & UX Pass, Design Freeze (UI Sprint 2, HomeroomAssignment vertical slice, frozen 2026-07-30)

Reached through a dedicated Architecture & UX Pass grounded in direct inspection of `App\Core\Concerns\HasTemporalAssignment` (the shared trait behind every effective-dated fact in this codebase), all three real consumer models (`SectionAssignment`, `HomeroomAssignment`, `TeacherAssignment`), their migrations, their one existing Action each (`AssignSectionAction` et al. — create-only, no reassign), the seeded `reason_codes` catalog, and `docs/developer/temporal-pattern.md` — not assumption. Reviewed and approved with no reopening of the design; two additions (Employee/ReasonCode lookup endpoints) confirmed as real, necessary scope rather than creep.

### 30.1 Why This Is a Third Pattern, Not an Extension of §28

§28's List/Detail/Form pattern serves standalone Reference Master Data catalogs (browse *all* Academic Years). A temporal assignment record has no such standalone meaning — every one of the three real consumers is a fact scoped to exactly one anchor (`SectionAssignment` → `Enrollment`; `HomeroomAssignment` → `Section`; `TeacherAssignment` → `SubjectOffering`), and `HasTemporalAssignment` itself enforces this is never edited in place: a record is only ever **created**, **closed** (`closeAssignment()`, a legitimate conclusion), or **cancelled** (`cancelAssignment()`, a correction — the record should never have existed). There is no `Edit`. §28's shared shells (`EntityListPage`/`EntityDetailPage`/`EntityFormPage`) are not reused here — this pattern is genuinely new, sized specifically for this shape.

### 30.2 Workspace Structure

A new, independent top-level workspace, **`Assignments`** — deliberately not folded into `Academic` (which §29's own closure confirmed is Reference/Master Data specifically; an Assignment Engine with its own lifecycle and timeline is a different concern, per explicit product decision). Flat Tab Switcher inside it, mirroring §28.2's own precedent exactly (one tab per temporal entity — `Homeroom Assignments` wired this slice; `Section Assignments`/`Teacher Assignments` remain unwired tabs, added only when their own slice is built, never spun up speculatively).

```
/w/assignments/homeroom                         → pick a Section (async-select landing, not a row list)
/w/assignments/homeroom/{sectionId}              → that Section's full assignment Timeline
/w/assignments/homeroom/{sectionId}/new          → full page: assign a new homeroom teacher
/w/assignments/homeroom/{sectionId}/{id}/close   → full page: close an assignment
/w/assignments/homeroom/{sectionId}/{id}/cancel  → full page: cancel an assignment
```

Reuses the nested index+splat router structure §8.5 already made binding, verbatim — `router.tsx` itself is untouched; `Assignments` is simply a second real consumer of `useWorkspaceSubPath()`, proving §8.5's own claim that the mechanism generalizes beyond Academic. Breadcrumb: Home → Assignments → Homeroom Assignments → [Section name].

**No anchor type-ahead is built in this slice.** Section (the anchor) is already a real, bounded-size entity (§29) — the existing `async-select` field kind, which preloads its full option set once, is reused verbatim for anchor selection, the same way it already serves Academic Year/Grade Level/Branch pickers. A genuine live-search combobox is deferred, disclosed explicitly, not built ahead of a real need: `SectionAssignment`'s own anchor (`Enrollment`, effectively "search for a student") and `TeacherAssignment`'s (`SubjectOffering`, a composite Subject+Section+Term search) are the entities likely to actually need it, when their own slice is built — not Homeroom's.

### 30.3 Timeline — the New List-Analog Pattern

Replaces `DataTable` entirely for this pattern (no pagination/column-sort/search toolbar — a timeline is read as a whole, not paged through). Rows ordered by `effective_from` descending. Each row shows: the period (`effective_from` → `effective_until`, or "Ongoing" when null), a status Badge, the reason (when set), and who ended it (when set).

**Which row is "currently active" is computed from dates client-side (`asOf(today)`), never trusted from the stored `status` column alone** — this is not a UI convenience, it is `HasTemporalAssignment`'s own documented invariant (`temporal-pattern.md`: *"status is administrative, never authoritative"*) reproduced faithfully on the frontend rather than re-deciding it. At most one row is visually highlighted as current; every other row renders with less visual weight.

Status Badge mapping (reusing existing `Badge` variants, no new color introduced): `scheduled` → `default`, `active` → `success`, `ended` → `muted`, `cancelled` → `destructive` (additionally struck-through, marking it visually as "never should have existed," distinct from `ended`'s plain muted treatment).

Row actions: **Close** renders only on the current active/scheduled row; **Cancel** renders on any row not already cancelled. No row ever offers Edit.

### 30.4 Three Full-Page Actions, No Modal, No Edit

Per §7.2's already-frozen Form Template, extended here rather than reopened:

- **Create (Assign):** an `async-select` Employee picker + `effective_from` (date, defaulting today) — nothing else; status is never a form field, it's always computed. A 422 from the backend's own `guardAgainstOverlap()` (a genuine competing-assignment conflict) surfaces as an ordinary inline field error via the existing `mapServerErrors` — no overlap logic is duplicated on the frontend, matching §28.6's identical rule for Consistency Invariants.
- **Close:** a reason `async-select` (populated from the real, seeded `reason_codes` catalog for the `homeroom_teacher_assignment` context) + an optional `effective_until` (defaulting today) + a `useConfirm` gate before submit.
- **Cancel:** a reason `async-select` (same catalog) + a `useConfirm` gate using the `destructive` variant — a deliberately heavier visual gate than Close's, since cancelling invalidates a record rather than concluding it.

Close and Cancel are two distinct actions/routes, never a single "end" action with a type toggle — they are semantically different operations (`closeAssignment()` vs `cancelAssignment()` are different trait methods with different meaning), and the UI states that difference plainly rather than collapsing it for convenience.

**No `Reassign` action exists, deliberately.** `temporal-pattern.md` states outright that opening the next period after closing one is each consuming module's own business logic — no such orchestrating Action exists in the backend for any of the three entities today. This slice does not invent one either: reassigning a homeroom teacher is Close, then Create, as two explicit, separate steps — matching exactly what the backend already supports. An atomic `ReassignHomeroomTeacherAction` remains a legitimate future backend task if a real commercial need for it surfaces, scoped and built independently, not smuggled into this slice.

### 30.5 New Backend Prerequisites (real scope, not creep — same discipline as `BranchController` in §29)

Two minimal, read-only lookup endpoints, neither of which the UI can function without:

- **`EmployeeController`** (`app/Modules/Identity/Http/Controllers/` or `app/Modules/People/Http/Controllers/`, mirroring `BranchController`'s own shape exactly) — `GET /employees?status=active` returning `{id, name_en, name_ar}` per employee, `name_*` resolved via `Person::name()->fullNameEn()`/`fullNameAr()` (no direct name column exists on `Employee` itself), filtered to `lifecycle_status = 'active'`.
- **`ReasonCodeController`** (`app/Core/Http/Controllers/`, since `ReasonCode` is a Core concern, not Academic's) — `GET /reason-codes?context={context}` returning `{code, label}` per active reason code for that context. Generic across all three temporal entities from day one — the `context` query param is the only thing that varies, so this endpoint needs building exactly once for all of UI Sprint 2, not once per entity.

### 30.6 `HomeroomAssignmentController` — Timeline/Action-Shaped, Not `AcademicMasterDataController`-Shaped

Deliberately **not** a subclass of §29's `AcademicMasterDataController` — that shared base's `update`/`setStatus` shape has no meaning here (there is no generic "status" to PATCH; `close`/`cancel` are distinct, reason-and-date-carrying operations, and there is no `update` at all). A new, purpose-built controller: `index` (list a Section's own timeline, `?section_id=`), `store` (create, delegates to a locked-anchor create path mirroring `AssignHomeroomTeacherAction`'s own existing discipline — reused, not reimplemented), `close` (calls the model's inherited `closeAssignment()` directly, no dedicated Action needed, matching Sprint B's own precedent), `cancel` (calls `cancelAssignment()` directly, same reasoning). No `destroy()`, structurally, matching §28.7's discipline extended to this pattern: a temporal record is retired via `cancelAssignment()`, never deleted.

### 30.7 Reusable Now vs. Homeroom-Specific

**Reusable verbatim for `SectionAssignment`/`TeacherAssignment` when their own slice is built:** the entire Timeline component (ordering, `asOf`-computed active highlighting, the four status Badges); the Close/Cancel page templates (only the `context` string passed to `ReasonCodeController` changes); `ReasonCodeController` itself (generic already); the nested-route/breadcrumb shape; the "no Reassign, no Edit, two full-page actions" rules themselves.

**Specific to `HomeroomAssignment`, expected to differ per entity:** the anchor type and its picker (`Section` via `async-select` here; `Enrollment`/`SubjectOffering` likely need real search later, §30.2); the Create form's "other party" field (`Employee` here; `Section` for `SectionAssignment`); `EmployeeController` itself (reused only by `TeacherAssignment` later, since both assign an Employee — not reused by `SectionAssignment`, which assigns a Section).

### 30.8 Design Freeze Status

**Design Freeze: APPROVED (2026-07-30).** No reopening requested; `EmployeeController`/`ReasonCodeController` confirmed as real, necessary scope. Implementation begins next: `EmployeeController`, `ReasonCodeController`, `HomeroomAssignmentController` (backend), the Timeline/Create/Close/Cancel templates and the new `Assignments` workspace (frontend), live verification, independent review, a fix cycle if needed, then closure — the same full cycle applied to every prior phase in this project. `SectionAssignment` and `TeacherAssignment` are explicitly deferred until this slice closes, proving the pattern once before repeating it, not building three at once.

### 30.9 Implementation: Backend

`app/Modules/People/Http/Controllers/EmployeeController.php` (new) — `GET /employees` returns `{id, name_en, name_ar}` for `lifecycle_status = 'active'` employees, names resolved via `Person::name()->fullNameEn()/fullNameAr()` (no direct name column on `Employee`), gated by `academic.manage-catalog` (super admin bypass). `app/Core/Http/Controllers/ReasonCodeController.php` (new) — `GET /reason-codes?context=` returns `{code, label_en, label_ar}`, gated by `auth:sanctum` alone (deliberately cross-module, no Academic-specific permission — §30.5's own reasoning). A real bug found live at this point: `ReasonCode::get(['code','label'])` alone serializes `label` through Spatie `HasTranslations`'s locale-resolved accessor — a single string in whatever the backend's own app locale happens to be, not the admin UI's chosen language. Fixed by explicitly mapping to `{code, label_en, label_ar}` via `getTranslation()`, matching this codebase's own established bilingual-field convention (every other bilingual field is wired as separate `_en`/`_ar` fields, never one locale-resolved string) — the exact convention this bug had violated.

`app/Modules/Academic/Http/Controllers/HomeroomAssignmentController.php` (new) — `index`/`store`/`close`/`cancel`, no `update`/`destroy`, confirmed by a dedicated router-table test. Deliberately not extending `AcademicMasterDataController` (§30.6). Establishes this codebase's first HTTP-layer convention for `HasTemporalAssignment`'s own `RuntimeException`/`InvalidArgumentException` failures (overlap, closed academic year, unregistered/malformed reason code, `DateRange`'s half-open-interval invariant): every catch returns a 422 with Laravel's standard `{message, errors: {field: [...]}}` shape (never a bare `message`, and never a raw 500) so the frontend's existing `mapServerErrors` renders an ordinary inline field error, exactly as §30.4 specifies. `store()`'s failures attach to `employee_id` (Section is fixed by the route, not a form field); `close()`'s failures are classified by the exception's own known message content — reason-related text attaches to `reason_code`, everything else (date-range/overlap, both genuinely about the record's period) attaches to `effective_until`; `cancel()`'s failures are unambiguously reason-related (cancelled records skip the overlap guard entirely per `guardAgainstOverlap()`'s own early return), so they always attach to `reason_code`.

Two small, necessary shared-model additions, both required by the Timeline's own field requirements (§30.3: "the reason (when set), and who ended it (when set)"), neither anticipated at freeze time: `HasTemporalAssignment::reasonCode(): BelongsTo` (the trait, since `reason_code_id` is a shared column and `ReasonCode` lives in Core alongside it) and `HomeroomAssignment::endedBy(): BelongsTo` → `App\Modules\Identity\Models\User` (the model itself, NOT the trait — Core must never depend on a Foundation module per `deptrac.yaml`'s `Core: []` ruleset entry; each future `HasTemporalAssignment` consumer in a Domain module declares its own one-line copy of this relation).

`AssignHomeroomTeacherAction::execute()` gained an optional `Carbon|string|null $effectiveFrom` parameter (previously hardcoded `now()`, matching every sibling `Assign*Action` still today) — required because §30.4 makes `effective_from` a real, user-editable Create-form field, not an implicit "right now." This surfaced a second real bug, found only during the independent review below (§30.15 Finding 2): `status` was still unconditionally set to `'active'` regardless of the resolved date, so a homeroom teacher assigned weeks in the future showed a green "Active" badge today, before the assignment had actually started — the exact "status column lies" failure mode `HasTemporalAssignment`'s own docblock warns against. Fixed by computing `status` from the resolved date (`'scheduled'` when after today, `'active'` otherwise) rather than hardcoding it; `status` remains administrative-only (`asOf()`/`active()` never trust it either way) — the fix only makes the label agree with what `asOf()` will itself compute, not the other way around.

`app/Modules/Identity/Services/WorkspaceAccessResolver.php` gained an `assignments` entry, reusing `academic.manage-catalog` verbatim (same reasoning as `academic`'s own entry immediately above it) — without this, the workspace built no left-nav link and was unreachable regardless of how correct its own code was; found and fixed during live verification, not anticipated in the original design pass.

### 30.10 Implementation: Frontend

`packages/contracts/src/assignments/assignments.schemas.ts` (new) — `HomeroomAssignmentSchema`, `EmployeeOptionSchema`, `ReasonCodeOptionSchema`, all plain `{data: [...]}` envelopes (§30.3: a Timeline is read as a whole, never paged — no `paginatedSchema()` reuse here).

`admin/src/platform/timeline/` (new, shared pattern per §30.7 — genuinely entity-agnostic, verified by grep during independent review to contain zero Homeroom/Employee/Section references outside comments): `timeline-metadata.ts` (`TimelineRow` type; `isCurrentAsOfToday()`, reproducing `HasTemporalAssignment::scopeAsOf()`'s own half-open-interval logic client-side rather than trusting the stored `status` column — §30.3's own binding requirement); `timeline.tsx` (the Timeline row list — ordering is the caller's responsibility via the API's own `orderByDesc('effective_from')`, the four status Badge variants, cancelled rows rendered with `line-through`, Close rendered only on the currently-covering-today row, Cancel rendered on any non-cancelled row); `assignment-action-form.tsx` (`CloseAssignmentForm`/`CancelAssignmentForm`, two distinct exports per §30.4's "never a single 'end' action with a type toggle" rule, reusable verbatim by `SectionAssignment`/`TeacherAssignment` — only the `context` string and confirm copy change per entity).

`admin/src/workspaces/assignments/` (new workspace) — `register.ts`/`assignments-workspace-page.tsx` (Tab Switcher: `homeroom` wired, `section`/`teacher` render a stub panel, matching Academic's own "entities added one at a time" precedent), `assignments-route.ts` (splat-path helper mirroring `entity-route.ts`'s shape, not reused directly — the segment shape is deeper), `providers/` (`homeroom-assignment-provider.ts`, `employee-options.ts`, `reason-code-options.ts` — both locale-aware, resolving `_en`/`_ar` from `i18n.language` rather than hardcoding English the way `loadBranchOptions`/`loadAcademicYearOptions` in the Academic workspace still do, `section-options.ts` reusing Academic's own `sectionProvider.list()` rather than a second REST shape), `homeroom/` (landing/timeline/create/close/cancel pages).

`WorkspaceAccessResolver` gaining the `assignments` entry (§30.9) was the frontend's own missing half too — the workspace's `register.ts` was correct from the start; nothing was reachable in the left nav until the backend visibility gate existed.

### 30.11 Bugs Found During Live Verification and Independent Review

Beyond the two already covered in §30.9 (ReasonCode label serialization, missing `WorkspaceAccessResolver` entry) and the `AssignHomeroomTeacherAction` status bug (§30.9, found by the independent review):

1. **Unsaved-changes-guard race on Create's save-then-navigate.** `HomeroomCreatePage` originally navigated synchronously inside its mutation's `onSuccess`, without first calling `reset()` — the exact race `entity-form-page.tsx`'s own docblock already documents and fixes (`useBlocker`'s re-registration effect only picks up a cleared `isDirty` on React's next commit, one tick too late for a `navigate()` firing in the same synchronous callback). Reproduced live: a successful "Assign" still popped the "Discard unsaved changes?" prompt on its own resulting navigation. Fixed identically to `entity-form-page.tsx`'s established pattern — `reset()` then route the navigation through its own state + effect, not synchronously.
2. **Server error shape didn't match `mapServerErrors`'s contract.** The first working version of `HomeroomAssignmentController`'s exception handling returned a bare `{message}` on a 422, which `mapServerErrors` silently ignores (it only renders an inline field error when `errors` is present) — meaning a real overlap/closed-year rejection would have surfaced only as the generic "Something went wrong" toast, losing the specific reason entirely, contradicting §30.4's own explicit text ("surfaces as an ordinary inline field error via the existing `mapServerErrors`"). Fixed before first live test by adding the `errors: {field: [...]}` shape described in §30.9.
3. **Same-day Close produces an empty, invalid `DateRange`.** Closing an assignment created today defaults `effective_until` to today too — an empty `[today, today)` interval, rejected by `DateRange`'s own half-open-interval invariant. Found live (reproduced with a freshly created same-day assignment), confirmed as a genuine 422 (not a raw 500) but initially misattributed to the `reason_code` field. Fixed by classifying the exception's own message content rather than assuming it's always reason-related (§30.9); a dedicated regression test (`HomeroomAssignmentControllerTest.php`, "found live") locks in the correct field attachment.
4. **Close's confirm dialog was visually identical to Cancel's (independent review Finding 1, blocking).** Both `CloseAssignmentForm` and `CancelAssignmentForm` passed `variant: 'destructive'` to `useConfirm()`, contradicting §30.4's own binding text: *"[Cancel] using the destructive variant — a deliberately heavier visual gate than Close's."* That sentence only holds if Close's own gate is not the destructive (red) variant. Fixed by changing `CloseAssignmentForm`'s confirm call to `variant: 'default'`; re-verified live (computed `background-color` of the dialog's own confirm button, confirmed no longer the destructive red).

Known, disclosed, deliberately-not-fixed minor gap (independent review's own non-blocking note): the raw `DateRange` exception message (item 3 above) is surfaced verbatim to the field error, including the value object's own class-name prefix ("DateRange: ..."), and is English-only. Not polished here — every other business-rule exception message in this codebase (`ClosedAcademicYearException`, the overlap guard's own message, etc.) is equally English-only and equally verbatim; fixing this would mean either duplicating `DateRange`'s message text per-locale in a shared Core value object used by many other consumers, or building a general server-side error-localization mechanism — both genuinely out of this slice's scope, not attempted here.

### 30.12 Live Verification

Docker backend, real login as `testuser`, real dev database (not a fixture, per this project's Fixture Revert discipline — every temporary Employee/Person/AcademicYear/GradeLevel/Branch/Section/HomeroomAssignment created for verification was force-deleted afterward; confirmed via a final reload showing the real seeded Section "A" untouched, still with its own empty timeline). Full flow exercised live end-to-end, twice (once before the fix cycle, once after): pick a Section (async-select) → empty timeline → Assign (a closed-year Section correctly rejected inline under Employee; a genuine open-year assignment created, status `active`) → Close (reason + future `effective_until`, confirm dialog now the default/non-destructive variant, row transitions to `Ended` with the resolved bilingual reason and `Ended by <real Person name>`) → Cancel (a second assignment, `entered_in_error`, destructive confirm, row transitions to `Cancelled`, struck through). Re-verified after the fix cycle: a future-dated Create now shows a `Scheduled` badge (not `Active`), correctly renders no Close action (a scheduled/future row is never "current" by `isCurrentAsOfToday`'s own date-only logic). Full pass repeated in Arabic/RTL (`dir="rtl"`, `lang="ar"` confirmed via the DOM) — every label, badge, and bilingual name resolved correctly (`Rod Legros` / `أحمد الرشيد`, `Entered in error` / `تم إدخالها بالخطأ`). Zero unexpected console errors throughout (the only errors logged were the deliberately-triggered 422s themselves, expected and correctly handled).

### 30.13 Backend Test Coverage

`tests/Feature/People/EmployeeControllerTest.php` (3 tests), `tests/Feature/Core/ReasonCodeControllerTest.php` (4 tests, including the bilingual-label regression), `tests/Feature/Academic/HomeroomAssignmentControllerTest.php` (12 tests — timeline ordering, create success, `effective_from` round-trip, closed-year 422, overlap 422, close success with `ended_by` resolution, unregistered/malformed reason 422, the same-day-Close field-attachment regression, cancel success, permission denial, no update/delete route), `tests/Feature/Academic/AssignHomeroomTeacherActionTest.php` (5 tests — including the `scheduled` vs `active` status regression for both a future and a same-day date), `tests/Feature/Identity/WorkspaceControllerTest.php` (1 new test covering the `assignments` entry).

### 30.14 Verification Results

Backend: `php artisan test` **533/533 passing** (23 new since §29's 510 baseline), `./vendor/bin/pint --test` clean (423 files), `./vendor/bin/deptrac analyse --no-progress` **0 violations** (confirming `HomeroomAssignment::endedBy()`'s reference to Identity's `User` correctly lives on the Domain-module model, not the Core trait). Frontend: `tsc -b` clean, `oxlint` clean (the same two pre-existing warnings, none new), `vitest run` 4/4 (no new automated frontend tests — verified live instead, matching §29's own precedent for this pattern).

### 30.15 Documentation-First Compliance Note and Independent Read-Only Repository Review

A first pass implemented, live-verified, and believed-closed the entire slice without updating this document, `docs/IMPLEMENTATION_PLAYBOOK.md`, or `CHANGELOG.md` — the identical Finding A already caught once before, in §29.7, for UI Sprint 1-B. A fresh independent agent, no prior context, caught it again as this phase's own **Finding 1 (blocking)**, alongside genuine code-level defects it found by re-deriving every claim from the actual code and re-running every command fresh rather than trusting the implementer's summary:

- **Finding 1 (blocking):** this document, the Playbook, and the CHANGELOG were not updated. Resolved by this section's own existence, plus the Playbook/CHANGELOG entries §30.16 below points to.
- **Finding 2 (blocking):** Close and Cancel's confirm dialogs were visually identical, contradicting §30.4's own binding text. Resolved and re-verified live (§30.9's `AssignHomeroomTeacherAction` status fix and §30.11 item 4).
- **Finding 3 (real, non-blocking but material):** a future-dated `effective_from` produced a lying `status: 'active'` badge. Resolved and covered by a new regression test (§30.9, §30.13).
- Two cosmetic notes, both addressed: `HomeroomAssignmentController` now extends `App\Http\Controllers\Controller` like every sibling controller in the module; the `DateRange` message-leak/localization gap was reviewed and deliberately left as a disclosed, out-of-scope limitation (§30.11's own closing paragraph), not silently ignored.

The review independently confirmed (not re-quoted from the implementer): §30.1/§30.6's controller-boundary rule holds structurally; §30.2's route shape matches exactly; §30.3's Timeline ordering/highlighting/Badges are correct; §30.4's three distinct full-page actions exist with no `Reassign` anywhere (grepped, zero hits); §30.7's genericity boundary holds (grepped `platform/timeline/*` for entity-specific terms, zero hits outside comments); `SectionAssignment`/`TeacherAssignment` remain completely untouched (`git diff` empty); bilingual discipline holds end-to-end, backend and frontend; deptrac is clean; no dead code, `console.log`, or TODO/FIXME in any touched file; and every command claimed clean was re-run fresh and genuinely was.

Following the fix cycle above (§30.9's `AssignHomeroomTeacherAction` fix, §30.11 item 4's confirm-variant fix, both re-verified live in §30.12, and this documentation itself), the slice is considered **closed on the same evidentiary bar applied to every prior phase in this project** — verified by re-derivation, not self-report, with every finding traceable to a concrete fix and a concrete re-verification.

### 30.16 Status

**UI Sprint 2, HomeroomAssignment vertical slice: COMPLETE AND CLOSED.** The pattern (Timeline, Close/Cancel templates, no-Edit/no-Reassign discipline, the Assignments workspace shell) is proven once, ready for `SectionAssignment` and `TeacherAssignment` to reuse per §30.7 — neither is started; both remain explicitly deferred, per `docs/IMPLEMENTATION_PLAYBOOK.md`'s own roadmap ordering.

---

## 31. SectionAssignment Workspace — Architecture & UX Pass, Design Freeze (UI Sprint 2, second vertical slice, frozen 2026-08-12)

Reached through a dedicated Architecture & UX Pass grounded in direct inspection of `SectionAssignment`, `AssignSectionAction`, `Enrollment`, `Person`'s `search_key` mechanism, every existing search-shaped endpoint in the backend (none found), and every existing frontend search/combobox component (`AsyncSelectField`, `search-input.tsx`, the global `SearchBar`, `cmdk`'s one existing consumer) — not assumption. Reviewed and approved across two rounds: five architectural decisions confirmed first (§31 throughout), then three interaction-level decisions (§31.3, §31.10, §31.8) confirmed second. **Design Freeze: APPROVED (2026-08-12).**

### 31.1 Why This Is a Real Test of §30's Pattern, Not a Repeat

`SectionAssignment` reuses §30's Timeline and Close/Cancel templates verbatim (§31.5, §31.6), proving §30.7's own claim that they generalize beyond Homeroom. But its anchor is structurally harder than Homeroom's: Homeroom's anchor (`Section`) is small and bounded, picked via the existing `async-select` field kind that preloads its full option set once. `SectionAssignment`'s anchor is `Enrollment` — reached only by finding a specific *Student*, a population with no small bound and no existing search mechanism anywhere in this codebase (§31.2). This is the first slice to genuinely need a live, debounced, server-side search — deliberately deferred at Homeroom's own freeze (§30.2: *"Section... is already a real, bounded-size entity... A genuine live-search combobox is deferred, disclosed explicitly, not built ahead of a real need: `SectionAssignment`'s own anchor... is the entit[y] likely to actually need it"*). This slice is that need, arriving on schedule.

A second, independent finding shaped this freeze: `AssignSectionAction` — built before UI Sprint 2 existed, during Phase 5 Sprint B — carries the exact same latent defect UI Sprint 2's own independent review found and fixed in `AssignHomeroomTeacherAction` (§30.9, §30.15 Finding 3): `effective_from` hardcoded to `now()`, `status` hardcoded to `'active'` with no date-based `scheduled` computation. Since this slice's own Create form makes `effective_from` a real, user-editable field (§31.6, matching §30.4's Create template exactly), leaving this defect unfixed would knowingly reproduce a bug this project has already found, documented, and fixed once. §31.11 makes fixing it part of this slice's own scope, not a follow-up.

### 31.2 Enrollment Search — API Contract

No existing endpoint anywhere in this backend performs a live person/student search (confirmed by inspection of `IdentityMaintenance`'s `MergeRequestController`, which expects already-known IDs, and `Admissions`, which has no `Http/Controllers/` at all). `Person::search_key` (a normalized consonant-skeleton equality key for `DuplicateDetectionService`'s own narrowing step) is a different mechanism for a different purpose — not reused here. This is genuinely new backend surface.

**New endpoint, in People (Foundation), not Academic (Domain) — §31.9 explains why:**

```
GET /enrollments/search?q={string}&academic_year_id={int}
```

**Two layering corrections made during implementation, not at freeze time (both surfaced and confirmed before writing any code, not silently resolved):** the response shape and the "which academic year" scoping originally drafted for this section both implicitly required `EnrollmentController` (People/Foundation) to reference `App\Modules\Academic\Models\AcademicYear`/`GradeLevel` (Domain) — a direct violation of `deptrac.yaml`'s `Foundation: [Core, Foundation, Administration]` ruleset (Domain is absent from that list), the exact same rule `Enrollment`'s own docblock already cites for why it has no `academicYear()`/`gradeLevel()` relations at all. Corrected as follows, both confirmed:

| Rule | Value |
|---|---|
| `q` | Required, minimum 2 characters (avoids an unbounded `LIKE '%a%'` scan on a 1-character query) |
| `academic_year_id` | **Required, client-supplied** — corrected from an original draft of implicit server-side resolution (`AcademicYear::where('status', 'active')`), which would have required `EnrollmentController` to reference Academic's `AcademicYear` model, a Foundation→Domain violation. "Which year is open" is Academic's own concern (matching `AssignSectionAction::assertAcademicYearIsOpen()`'s own placement) — the Academic-side frontend resolves it once via the already-existing `academicYearProvider` (the same loader Section's own async-select fields already use) before issuing a search, and passes the ID through. `EnrollmentController` applies it as a plain `Enrollment.academic_year_id` FK equality filter — no Academic-model reference of any kind, not even a query against the `academic_years` table. |
| Matched columns | `first_name_en`/`family_name_en`/`first_name_ar`/`family_name_ar` on `Person`, via `Enrollment → Student → Person`, plain substring `LIKE` — not `search_key` (a different, equality-based mechanism), not fuzzy/cross-script matching (out of scope, §31.12) |
| Enrollment status scope | `status = 'active'` only, always, non-negotiable from the client — an Enrollment that isn't active would be rejected by `AssignSectionAction`'s own `assertActive()` guard anyway; excluding it from search results avoids the user ever reaching that rejection |
| Result count | Server-side fixed cap (20), no `page`/pagination — this is a live combobox, not a paged list |
| Ordering | Alphabetical by student name, matching `EmployeeController::sortBy('name_en')`'s own precedent — no relevance ranking (full-text/Scout-based search is out of scope, §31.12) |

**`EnrollmentController` never trusts the client-supplied `academic_year_id` as proof the year is actually open** — it is used only to narrow the search result set, not as an authorization or business-rule decision. The real, final guard remains exactly where it already lived before this endpoint existed: `AssignSectionAction::assertAcademicYearIsOpen()`, checked again at the moment of `store()`. If the open year changes between a user's search and their submit (a real, if narrow, race), the Action rejects it correctly at that point — the search endpoint's own scoping is a UX convenience, never a trusted precondition.

**Response shape** (`grade_level_name`/`academic_year_name` corrected to raw FK ids — `EnrollmentController` does not know these two are even names, only that they are numbers; the Academic-side frontend resolves both to display labels via the same already-existing `academicYearProvider`/`gradeLevelProvider`, reused, not rebuilt):
```json
{
  "data": [
    {
      "enrollment_id": 123,
      "student_name_en": "...", "student_name_ar": "...",
      "student_public_id": "01K...",
      "branch_id": 2,
      "branch_name_en": "...", "branch_name_ar": "...",
      "academic_year_id": 5,
      "grade_level_id": 12,
      "status": "active"
    }
  ]
}
```
`branch_name_en/ar` stays a direct response field — `Branch` lives in Identity, itself a Foundation module (`deptrac.yaml`'s own regex groups `Identity` alongside `People`), so `Enrollment::branch()`'s existing relation crosses no boundary at all.

**Considered and deliberately rejected:** a `current_section_name` field per result (would require an extra `SectionAssignment` join per row — and `SectionAssignment` itself lives in Academic, the same Foundation→Domain problem this correction just solved for `AcademicYear`/`GradeLevel`, reinforcing that this field would have needed solving too had it been kept). An Enrollment that already has an active `SectionAssignment` is already correctly rejected by `guardAgainstOverlap()` at Create time, surfaced via the same inline-field-error mechanism §30.4 already established — duplicating that signal into the search response itself would add a join for information the flow already handles correctly downstream.

**A second endpoint, same controller, required by §31.5/§31.6 (not explicitly requested when this slice was scoped, but a structural necessity — disclosed, not silently added):**

```
GET /enrollments/{id}
```

Needed because the Timeline/Create/Close/Cancel pages must resolve an Enrollment's own display context (student name, branch, grade) on direct load — exactly as `HomeroomTimelinePage` already calls `sectionProvider.get(sectionId)` for the equivalent Homeroom case.

Both endpoints live on a new `App\Modules\People\Http\Controllers\EnrollmentController` — minimal and read-only, matching `EmployeeController`'s own shape exactly (§31.9).

### 31.3 Landing Page `/w/assignments/section` — Interaction Model

Structurally different from Homeroom's landing page (a single bounded `async-select` + explicit "View" button): a live search box (§31.8's new `SearchCombobox`). Confirmed interaction model:

- **Selecting a result navigates immediately** to `/w/assignments/section/{enrollmentId}` — no separate confirm button, matching this codebase's own existing `SearchBar` interaction (select = navigate), not Homeroom's pick-then-confirm shape. A deliberate, confirmed divergence from Homeroom's own landing page, not an oversight.
- Empty/loading/no-results states, in order: a hint before 2 characters are typed ("Type a student's name to search..."); a loading indicator while the debounced query is in flight; a plain "No matching results" when the query returns nothing.
- **The "no open Academic Year" case is deliberately not distinguished from an ordinary empty-results state.** Considered explicitly (unlike Configuration Platform's own System-Initialization-vs-Operational-Empty-State distinction, §26/Frontend Track F1) and rejected here: a genuinely rare administrative-configuration anomaly, not a normal state a fresh installation's user would routinely hit — building a distinct signal for it now would be speculative complexity ahead of a real, observed need. A generic "no matching results" message covers this case adequately for now.

### 31.4 Route Structure — [Reused verbatim from §30.2]

```
/w/assignments/section                              → Landing (search)
/w/assignments/section/{enrollmentId}                → Timeline
/w/assignments/section/{enrollmentId}/new            → Create
/w/assignments/section/{enrollmentId}/{id}/close     → Close
/w/assignments/section/{enrollmentId}/{id}/cancel    → Cancel
```

Same nested-splat dispatch shape `AssignmentsWorkspacePage` already implements for `homeroom`; the `section` tab already exists as an unwired stub (§30.2) and is wired for real here. No change to `router.tsx`, `WorkspaceRoutePage`, or `assignments-route.ts`'s own helpers.

### 31.5 Timeline Presentation — [Reused from §30.3, one entity-specific slot]

The shared `Timeline` component (`platform/timeline/timeline.tsx`), its `asOf(today)`-computed highlighting, the four status Badges, and struck-through cancelled rows are all reused verbatim — no change to `platform/timeline/*` files. The one entity-specific slot §30.7 always expected to vary (`primaryLabel`) is resolved in a new `section-timeline-page.tsx`, not the shared component: for `SectionAssignment`, `primaryLabel` is the assigned **Section**'s own display label (composed from its grade level and section name, e.g. "Grade 5 – A" — resolved by the page, not hardcoded into `Timeline` itself), the mirror image of Homeroom's own `primaryLabel` (there, the Employee's name — the "other party" being assigned to a fixed Section anchor; here, the Section itself is the "other party" being assigned to a fixed Enrollment anchor).

### 31.6 Create / Close / Cancel Forms

**Close/Cancel: reused verbatim from §30.4/§30.7.** `CloseAssignmentForm`/`CancelAssignmentForm` (`platform/timeline/assignment-action-form.tsx`) are called with `context = 'section_assignment'` (§31.2's seeded reason codes: `section_transfer`, `entered_in_error`) and this slice's own copy — zero changes to the shared component files, the first real proof of §30.7's "reused verbatim... only the `context` string changes" claim.

**Create: reused shape, one real extension.** The "other party" field is `Section` — small and bounded, exactly like Homeroom's own anchor picker, so it reuses the existing `async-select` field kind and the existing `sectionProvider.list()` call (no new endpoint). The extension: the options are filtered client-side to Sections whose `branch_id`/`academic_year_id`/`grade_level_id` all match the target Enrollment's own three values (already known from `GET /enrollments/{id}`, §31.2) — Section stays small enough that this filtering needs no new backend query, but it materially reduces how often a user can pick a Section the Consistency Invariant will reject (§31.7), rather than only catching it after a wasted round-trip.

### 31.7 Consistency Invariant — No Duplicated Business Rules in Frontend

`SectionAssignment::booted()`'s Consistency Invariant (branch/academic_year/grade_level must agree between `Enrollment` and `Section`) is enforced exactly once, server-side, exactly where it already lives — never re-implemented as frontend validation logic, per §30.4/§30.6's identical rule for Homeroom's overlap guard. §31.6's Section-filtering makes the invalid case rare, not impossible; any failure that still reaches the server (`InvalidArgumentException` from the Invariant, `RuntimeException` from `guardAgainstOverlap()`, `ClosedAcademicYearException`, `EnrollmentNotActiveException`) is converted by `SectionAssignmentController` into the same `{message, errors}` 422 shape §30.9 established, attached to `section_id` — the real, present form field here (unlike Homeroom, where the equivalent failures attached to `employee_id` because Section was fixed by the route there; here Enrollment is fixed by the route and Section is the form field, so the attachment target is the mirror image).

### 31.8 `SearchCombobox` — New Reusable Platform Primitive

`admin/src/platform/forms/search-combobox-field.tsx` (new) — built on `cmdk` (`Command`/`CommandInput`/`CommandList`/`CommandItem`), the only dependency already in `admin/package.json` suited to this (currently used in exactly one place, the Command Palette, never yet as a reusable form-field primitive). Requires a new `platform/components/ui/command.tsx` wrapper (cmdk has no existing shared UI wrapper the way Radix's `Select` already has one in `ui/select.tsx`).

```ts
{
  control, name, label,
  search: (query: string) => Promise<{ value: string; label: string; description?: string }[]>,
  minQueryLength?: number,  // default 2
  debounceMs?: number,      // default 250, matching the existing SearchBar's own precedent
  emptyMessage?: string,
}
```

`description` (confirmed, §31 review round 2) carries the disambiguating context a bare name can't — grade/branch/academic year — so two same-named students remain distinguishable in the result list without inventing a richer, more complex result-item shape.

**Boundary, matching `AsyncSelectField`'s own discipline exactly:** this component knows nothing about Enrollment, Student, or any other domain concept — the caller supplies `search()` and consumes the selected `value`. It lives in `platform/forms/`, not `workspaces/assignments/`, specifically so `TeacherAssignment`'s own `SubjectOffering` search (Subject + Section + Term, composite) can reuse it later without duplication.

### 31.9 Backend Ownership & Layering

| File | Module | Status |
|---|---|---|
| `EnrollmentController` (`search`, `show`) | People (Foundation) | New |
| `SectionAssignmentController` (`index`/`store`/`close`/`cancel`, no `update`/`destroy`) | Academic (Domain) | New, mirrors `HomeroomAssignmentController`'s shape exactly |
| `AssignSectionAction` | Academic (Domain) | Modified — §31.11 |
| `SectionAssignment::endedBy()` | Academic (Domain) | New — same reasoning as `HomeroomAssignment::endedBy()` (§30.9): `ended_by_id` → `App\Modules\Identity\Models\User` cannot live on the shared `HasTemporalAssignment` trait (Core must never depend on a Foundation module, `deptrac.yaml`'s `Core: []` ruleset), so each Domain-module consumer declares its own copy |
| `SectionAssignment::reasonCode()` | — | Reused, inherited from `HasTemporalAssignment` already |

`EnrollmentController` living in People, consumed by Academic's `Assignments` frontend, is the same direction `EmployeeController` already established for Homeroom — `deptrac.yaml`'s `Domain: [Core, Foundation, Administration]` ruleset permits this; no new dependency direction, no boundary risk.

### 31.10 Permissions

Both new controllers reuse `academic.manage-catalog` — no new permission invented. `EnrollmentController`'s own choice mirrors `EmployeeController`'s already-accepted precedent exactly: a People-module controller gated by an Academic permission is correct when its only real consumer is Academic's own Assignments workspace, not a new pattern requiring separate justification. `WorkspaceAccessResolver` needs no change — the `assignments` workspace entry already exists (§30.9); `section` is a tab inside it, not a new workspace.

### 31.11 Known Pre-Existing Gap Closed As Part Of This Slice

`AssignSectionAction::execute()` (Phase 5 Sprint B, predates UI Sprint 2 entirely) hardcodes `'effective_from' => now()` and `'status' => 'active'`, unconditionally — the exact defect UI Sprint 2's own independent review found and fixed in `AssignHomeroomTeacherAction` (§30.9, §30.15 Finding 3). Fixed here identically, before this slice's own Create form goes live (not after, and not deferred): an optional `Carbon|string|null $effectiveFrom` parameter, with `status` computed from the resolved date (`'scheduled'` when after today, `'active'` otherwise) rather than hardcoded. `status` remains administrative-only throughout (`asOf()`/`active()` never trust it) — this only makes the label agree with what `asOf()` will itself compute.

### 31.12 Out of Scope

- `TeacherAssignment` — still deferred; `SearchCombobox` (§31.8) is built generically enough for it to reuse later, but its own `SubjectOffering` search is not built here.
- Full-text/relevance-ranked search (the Scout-based abstraction `search-provider.ts`'s own docblock already flags as future, Blueprint Addendum D5) — plain substring `LIKE` only.
- Reusing `Person::search_key` for this search — a different mechanism (equality-based duplicate-detection narrowing), not a live substring search.
- `current_section_name` in search results (§31.2, considered and rejected).
- Branch Transfer, or any cross-branch `Enrollment` mutation — unrelated to this slice, already out of scope per Phase 5's own Architecture Pass.
- A general-purpose Student directory/profile page — `EnrollmentController` is a minimal, read-only lookup for this assignment flow specifically, not a new Student management surface.

### 31.13 Migration / Schema Changes — None

`section_assignments` already carries every column this slice needs (`enrollment_id`, `section_id`, `effective_from`, `effective_until`, `status`, `reason_code_id`, `ended_by_id`) — confirmed by direct inspection, not assumed. `people`/`students`/`enrollments` already carry every column `EnrollmentController` needs. No new column, no new index: the research behind this freeze found no evidence requiring one (no institution-scale figure exists anywhere in this repository's own docs, §31.2), and adding one without a proven need would be exactly the "prediction, not promotion" this project has consistently avoided elsewhere. If real-world query performance ever demands one, that is a disclosed future follow-up, explicitly not part of this Design Freeze.

### 31.14 Reused / Extended / New — Summary

| Piece | Status |
|---|---|
| `Timeline` component, its four Badges, `asOf(today)` logic | Reused verbatim |
| `CloseAssignmentForm`/`CancelAssignmentForm` | Reused verbatim (only `context` + copy change) |
| Route/breadcrumb nested-splat shape, Tab Switcher | Reused verbatim |
| `{message, errors}` 422 convention | Reused, same shape, different field target (§31.7) |
| `async-select` for the Create form's Section field | Reused, with client-side filtering added (§31.6) |
| `EmployeeController`-shaped minimal lookup pattern | Reused as the template for `EnrollmentController` |
| `primaryLabel` per Timeline row | Entity-specific slot, filled per §30.7's own expectation |
| Anchor picker (Section async-select → live student search) | New |
| `SearchCombobox` platform primitive | New |
| `EnrollmentController` (People) | New |
| `SectionAssignmentController` (Academic) | New |
| `AssignSectionAction`'s `effective_from`/`status` fix | New fix, closing a pre-existing gap (§31.11) |

### 31.15 Design Freeze Status

**Design Freeze: APPROVED (2026-08-12).** All five architectural decisions and all three interaction-level decisions (§31.3, §31.8, §31.10's landing/no-results/result-shape questions) confirmed. Implementation begins next, in this order: `AssignSectionAction` fix → `EnrollmentController` → `SectionAssignmentController` → `SearchCombobox` platform primitive → Landing → Timeline → Create → Close → Cancel — live verification, full backend/frontend verification suite, then an independent read-only repository review and a fix cycle if needed, before this section is updated to reflect closure. `TeacherAssignment` remains explicitly deferred.

### 31.16 Implementation: Backend

`AssignSectionAction::execute()` gained the same fix §30.9/§30.15 already applied to `AssignHomeroomTeacherAction`: an optional `Carbon|string|null $effectiveFrom` parameter (this Action predates UI Sprint 2 entirely — Phase 5 Sprint B — and had carried the identical hardcoded-`now()`/hardcoded-`'active'` defect the whole time, undiscovered until §31.1 flagged it during this slice's own architecture pass), and `status` computed from the resolved date (`'scheduled'` when after today, `'active'` otherwise) rather than hardcoded.

`app/Modules/People/Http/Controllers/EnrollmentController.php` (new) — `search()` (`GET /enrollments/search?q=&academic_year_id=`) and `show()` (`GET /enrollments/{id}`), both gated by `academic.manage-catalog` (mirroring `EmployeeController`'s own precedent for a People-owned lookup consumed only by Academic today). Contains, by design and independently confirmed twice (once during implementation via the two §31.2 corrections, once by the independent review below), zero reference of any kind to `App\Modules\Academic\Models\AcademicYear`/`GradeLevel` — `academic_year_id` arrives as a required client parameter, applied only as a plain `Enrollment.academic_year_id` FK equality filter, never trusted as proof the year is genuinely open (`AssignSectionAction::assertAcademicYearIsOpen()` remains the real, final guard at `store()` time). `app/Modules/People/Models/Enrollment.php` gained integer casts on `student_id`/`academic_year_id`/`branch_id`/`grade_level_id` — a real, necessary fix found live: `EnrollmentController` is this model's first JSON API surface, and without the casts a freshly-fetched row round-trips its FK columns as raw driver strings, the identical gap §29.3 already found and fixed for `Term`/`Section`. `branch_id` (not just `branch_name_en/ar`) is included in the response specifically because the frontend's Create-form Section filter (§31.6) needs all three raw FK values, matching `SectionAssignment`'s own three-column Consistency Invariant exactly.

`app/Modules/Academic/Http/Controllers/SectionAssignmentController.php` (new) — `index`/`store`/`close`/`cancel`, no `update`/`destroy`, mirroring `HomeroomAssignmentController`'s shape and its `{message, errors: {field}}` 422 convention exactly. `store()`'s failures attach to `section_id` — the mirror image of Homeroom's own `employee_id` attachment, since here Enrollment is fixed by the route and Section is the real form field. `close()`'s classification (reason-related exception text → `reason_code`; everything else, genuinely about the record's own period → `effective_until`) reuses the identical approach §30.9 established, unchanged. `app/Modules/Academic/Models/SectionAssignment.php` gained its own `endedBy(): BelongsTo` → `App\Modules\Identity\Models\User` — not on the shared `HasTemporalAssignment` trait, same reasoning as `HomeroomAssignment::endedBy()` (Core must never depend on a Foundation module); Academic (Domain) depending on Identity (Foundation) is the allowed direction per `deptrac.yaml`'s own ruleset, independently re-confirmed by direct inspection of the file during the review below, not merely re-asserted.

### 31.17 Implementation: Frontend

`packages/contracts/src/assignments/assignments.schemas.ts` gained `EnrollmentSchema` (raw `academic_year_id`/`grade_level_id`/`branch_id` as numbers, never resolved names — the wire-level expression of §31.2's own layering correction) and `SectionAssignmentSchema` (mirrors `HomeroomAssignmentSchema` exactly).

`admin/src/platform/components/ui/command.tsx` (new) — thin styled wrappers over `cmdk` (§31.8), matching `ui/select.tsx`'s own separation-of-concerns convention. `admin/src/platform/forms/search-combobox-field.tsx` (new) — the debounced live-search RHF field itself, genuinely domain-agnostic (independently confirmed by the review below: zero Enrollment/Student/Section references), ready for `TeacherAssignment`'s own future `SubjectOffering` search to reuse without duplication.

`admin/src/workspaces/assignments/providers/` gained `enrollment-provider.ts` (search/show), `academic-context.ts` (`loadOpenAcademicYear()`/`loadGradeLevelNameMap()`, reusing the existing `academicYearProvider`/`gradeLevelProvider` rather than rebuilding Academic-side lookups inside a People-owned file), `section-assignment-provider.ts`, and `section-options-for-enrollment.ts` (the §31.6 three-field client-side Section filter — `branch_id`/`academic_year_id`/`grade_level_id` all three, not a subset).

`admin/src/workspaces/assignments/section/` gained the five pages (`section-landing-page`, `section-timeline-page`, `section-create-page`, `section-close-page`, `section-cancel-page`, `constants`), wired into `assignments-workspace-page.tsx`'s previously-unwired `section` tab stub. `locales/{en,ar}.json` gained matching `section.*` key sets (58 keys each, parity confirmed).

### 31.18 Bugs Found During Live Verification

1. **Layering corrections, both surfaced and resolved before code was written, not after (§31.2's own "two layering corrections" note).** The original API-contract draft implicitly required `EnrollmentController` to reference Academic's `AcademicYear`/`GradeLevel` models twice over — once for the response shape (resolved names instead of raw ids), once for the "which year is open" scoping (a server-side `AcademicYear::where('status','active')` query). Both would have violated `deptrac.yaml`'s `Foundation: [Core, Foundation, Administration]` ruleset. Caught during implementation, before either line of code was written, by re-deriving the actual module boundaries rather than assuming the original draft was safe — corrected to raw FK ids (frontend resolves via existing providers) and a required client-supplied `academic_year_id` (resolved Academic-side, applied People-side only as a plain equality filter) respectively.
2. **Missing FK integer casts on `Enrollment`**, found live via a genuine `1 !== '1'` test failure once `EnrollmentController::show()` performed this model's first real fresh-fetch-then-serialize round trip — the identical class of bug §29.3 already found for `Term`/`Section`, now closed for `Enrollment` too (§31.16).
3. **A hardcoded English name in `section-create-page.tsx`'s breadcrumb** (`enrollmentQuery.data.student_name_en` used unconditionally, ignoring `i18n.language`) — found live by switching the admin UI to Arabic mid-verification and seeing the breadcrumb stay in English while every sibling page (Timeline, Close, Cancel) correctly flipped. Fixed to match the same `locale === 'ar' ? ... : ...` pattern already used consistently everywhere else in this slice and in Homeroom's own pages; independently re-checked by the review below across all five new pages, confirmed the only occurrence.

### 31.19 Live Verification

Docker backend, real login as `testuser`, real dev database (Fixture Revert discipline: every temporary AcademicYear/Branch/GradeLevel/Section/Person/Student/Enrollment/SectionAssignment created for verification was force-deleted afterward in FK-safe order, confirmed via a final zero-row query against every "Verify"-named fixture). Full flow exercised live end-to-end in both languages: search for a student by a name substring (confirmed scoped to both `status=active` and the resolved open Academic Year, confirmed via the actual network request's own query string) → select a result, navigating immediately with no confirm step (§31.3) → empty Timeline → Assign (Section picker correctly showing only the one Section matching the Enrollment's own branch/year/grade, not the full unfiltered list) → Close (reason + future `effective_until`, confirm dialog's own button color independently re-verified as the non-destructive variant — the same shared `CloseAssignmentForm` component Homeroom's own fix cycle already corrected, now proven to carry the fix automatically to a second consumer) → Cancel (a second Enrollment/assignment pair, destructive confirm, struck-through Cancelled row). Re-verified in Arabic/RTL after switching languages mid-session (`dir="rtl"`/`lang="ar"` confirmed via the DOM), including the breadcrumb-locale bug fix from §31.18. Zero unexpected console errors throughout both language passes.

### 31.20 Backend Test Coverage

`tests/Feature/People/EnrollmentControllerTest.php` (8 tests — substring name matching scoped correctly to both `status=active` and the given `academic_year_id`, explicit negative cases for each dimension, minimum-query-length validation, required-parameter validation, full single-enrollment context, permission denial, super-admin bypass), `tests/Feature/Academic/AssignSectionActionTest.php` (6 tests — including the `scheduled` vs `active` status regression for both a future-dated and a same-day assignment), `tests/Feature/Academic/SectionAssignmentControllerTest.php` (13 tests — timeline ordering, create success with the `effective_from` round-trip, closed-year 422 on `section_id`, withdrawn-enrollment 422, Consistency-Invariant-violation 422 (a Section entirely outside the Enrollment's own branch/year/grade), overlap 422, close success with `ended_by` resolution, same-day-close 422 correctly attached to `effective_until` not `reason_code`, unregistered-reason 422, cancel success, permission denial, no update/delete route).

### 31.21 Verification Results

Backend: `php artisan test` **556/556 passing** (27 new since §30's 533 baseline: 8 + 6 + 13), `./vendor/bin/pint --test` clean (427 files), `./vendor/bin/deptrac analyse --no-progress` **0 violations** (confirming both the People→Academic boundary `EnrollmentController` must never cross, and the Academic→Identity direction `SectionAssignment::endedBy()` is permitted to cross). `php artisan migrate:status` — nothing pending, confirming §31.13's zero-schema-change claim. Frontend: `tsc -b` clean, `oxlint` clean (the same two pre-existing warnings, none new), `vitest run` 4/4. `./vendor/bin/phpstan analyse` fails on a pre-existing `checkMissingIterableValueType` configuration incompatibility, confirmed via `git status` to be untouched by this slice (`phpstan.neon` unmodified) — not fixed here, logged as an independent Technical Debt Register item (`docs/IMPLEMENTATION_PLAYBOOK.md`) rather than expanding this slice's own scope to a pre-existing, unrelated tooling drift.

### 31.22 Documentation-First Compliance Note and Independent Read-Only Repository Review

A first pass implemented, live-verified, and believed-closed this entire slice without updating this document, `docs/IMPLEMENTATION_PLAYBOOK.md`, or `CHANGELOG.md` — the **third** occurrence of the identical gap already caught once in §29.7 (UI Sprint 1-B) and again in §30.15 (HomeroomAssignment). A fresh independent agent, no prior context, caught it a third time as this phase's own **sole finding, blocking**: *"the code itself is solid: deptrac-clean, fully tested, correctly mirrors the Homeroom precedent, and every §31 design-freeze decision... is faithfully implemented. The sole blocker is procedural, not technical."* Resolved by this section's own existence, plus the Playbook/CHANGELOG entries §31.23 below points to.

Beyond that one finding, the review independently re-derived (not re-quoted from the implementer) every substantive claim in this slice: re-ran deptrac fresh (0 violations) and additionally read `EnrollmentController.php`/`Enrollment.php` in full to confirm zero Academic-namespace references exist anywhere, not merely that the automated boundary check happened to pass; read `backend/deptrac.yaml` directly to independently confirm the People→Academic (forbidden) vs. Academic→Identity (permitted) asymmetry the design relies on; confirmed `academic_year_id` is genuinely client-supplied and never server-resolved, and that `AssignSectionAction::assertAcademicYearIsOpen()` remains the real, final guard, re-derived from the code and a passing test, not assumed from the docs; confirmed the Landing page's select-to-navigate interaction, the three-field Section filter, and the Consistency Invariant's single enforcement point by reading the actual implementation files; confirmed the `{message, errors}` field-attachment convention (`section_id` for `store()`, the same reason/date classification for `close()`) matches Homeroom's own established pattern rather than a subtly broken reimplementation; grepped all five new frontend pages for hardcoded-language regressions beyond the one already found and fixed (found none); confirmed `search-combobox-field.tsx`/`command.tsx` carry zero domain-specific references; confirmed `platform/timeline/*` files carry no diff from this slice (the reuse claim is real, not asserted); confirmed zero new migrations and nothing pending; confirmed `en.json`/`ar.json` key parity (58/58); grepped every touched file for dead code, `console.log`, and TODO/FIXME (zero found); and re-ran every verification command fresh, all matching the implementer's own reported results exactly (556/556, 0 violations, Pint/tsc/oxlint/vitest all clean, PHPStan's pre-existing failure independently reconfirmed via `git status`).

Following this documentation update, the slice is considered **closed on the same evidentiary bar applied to every prior phase in this project** — verified by re-derivation, not self-report, with the one finding traceable to a concrete fix (this section, plus §31.23/CHANGELOG/Playbook below).

### 31.23 Status

**UI Sprint 2, SectionAssignment vertical slice: COMPLETE AND CLOSED.** The pattern (Timeline, Close/Cancel templates, `SearchCombobox` platform primitive, the `Assignments` workspace shell) is now proven across two structurally different anchors — a small bounded picker (Homeroom's own Section) and a genuinely unbounded live search (this slice's own Enrollment/Student) — ready for `TeacherAssignment`'s own `SubjectOffering` search (a third, composite case) to reuse per §31.8/§31.12. `TeacherAssignment` remains explicitly deferred, per `docs/IMPLEMENTATION_PLAYBOOK.md`'s own roadmap ordering.

---

## 32. TeacherAssignment Workspace — Architecture & UX Pass, Design Freeze (UI Sprint 2, third and final vertical slice, frozen 2026-08-12)

Reached through a dedicated Architecture & UX Pass grounded in direct inspection of `SubjectOffering`, `TeacherAssignment`, `AssignTeacherToSubjectOfferingAction`, every model in `SubjectOffering`'s own relation chain (`Subject`/`Section`/`Term`/`AcademicYear`), `backend/deptrac.yaml`, and both prior slices' own reusable pieces (`platform/timeline/*`, `SearchComboboxField`, `EmployeeController`, `loadEmployeeOptions`) — not assumption. Reviewed and approved across two rounds: the anchor-selection shape confirmed first (§32.2, correcting an initial framing that assumed `SearchCombobox` would be reused here the way it was for SectionAssignment), then the full pass answering ten specific implementation questions confirmed second.

### 32.1 Why This Is the Third and Final Test of the Pattern

`TeacherAssignment` completes the pattern with a structurally different anchor from both prior slices: `SubjectOffering` is neither a single bounded FK (Homeroom's `Section`) nor a genuinely unbounded free-text-searchable population (SectionAssignment's `Enrollment`/Student) — it is a **composite of three bounded, interrelated dimensions** (`Subject` × `Section` × `Term`), unique on `(subject_id, section_id, term_id)`. This ruled out reusing `async-select` alone (no single FK to pick from) and, on closer inspection, ruled out reusing `SearchCombobox` too (§32.2 explains why a free-text live search is the wrong tool for a small, structured, three-facet space) — the correct shape turned out to be a third pattern this project hadn't needed yet: **cascading bounded pickers, with the final option list derived from real data rather than a raw catalog.**

Unlike `SectionAssignment` (whose anchor, `Enrollment`, lives in People/Foundation and forced two real `deptrac` layering corrections before code was written, §31.2), `SubjectOffering` and every model in its own relation chain (`Subject`, `Section`, `Term`, `AcademicYear`) live in `Academic` (Domain) — the same module a `SubjectOfferingController`/`TeacherAssignmentController` would itself live in. This was verified directly, not assumed from the pattern holding twice before (§32.8) — the research behind this freeze found **zero** layering conflicts in this slice, a genuinely different outcome from SectionAssignment's own experience, not a coincidence to take for granted.

A second, independent finding shaped this freeze exactly as it did for SectionAssignment: `AssignTeacherToSubjectOfferingAction` carries the **same** hardcoded-`now()`/hardcoded-`'active'` defect already found and fixed twice in its sibling Actions (`AssignHomeroomTeacherAction`, §30.9/§30.15; `AssignSectionAction`, §31.1/§31.11) — this is the third occurrence of an already-diagnosed bug class, and §32.10 makes fixing it part of this slice's own scope, not a follow-up.

### 32.2 Landing Page `/w/assignments/teacher` — Cascading Selection, Not Search

Three sequential dropdowns, **`Section → Term → Subject Offering`**, each an ordinary `async-select` (no new field kind, no `SearchCombobox`):

1. **Section** — `async-select`, options from the existing `sectionProvider.list()` (Academic workspace, unchanged), scoped to the currently open Academic Year via `loadOpenAcademicYear()` (already built for SectionAssignment, §31.2, reused verbatim).
2. **Term** — `async-select`, options from the existing `termProvider.list()` (unchanged), scoped to the same open Academic Year. Independent of Section (both are siblings under `AcademicYear`, neither narrows the other) — order between these first two steps is a low-stakes UX choice, not a technical constraint.
3. **Subject Offering** — **not** a raw `Subject` catalog dropdown. Once Section and Term are both chosen, a single new query (`GET /academic/subject-offerings?section_id=&term_id=`, §32.3) returns only the `SubjectOffering` rows that actually exist for that pair, labeled by their own Subject's name. **The option's `value` is the `subject_offering_id` itself**, not `subject_id` — selecting this dropdown *is* the final anchor selection, with no separate resolution step. This structurally prevents ever selecting a `(section, term, subject)` triple with no real `SubjectOffering` (§31's own Consistency-Invariant-avoidance discipline applied one level earlier, at the picker itself, rather than only at submit time) — if the derived list is empty, a dedicated empty-state message explains a real, plausible condition ("No subject offerings exist for this section and term yet"), not a generic zero-results message, since this is an expected state a real admin will hit often (many Section×Term pairs won't have offerings created yet), unlike SectionAssignment's own "no open Academic Year" edge case (§31.3), which was deliberately left undistinguished for being genuinely rare.

An explicit **"View"** button, not automatic navigation on the third selection — matching Homeroom's own landing page (§30.2), not SectionAssignment's select-and-navigate search result (§31.3). With three sequential decisions instead of one search-and-pick, a deliberate confirm step lets the user reconsider an earlier choice before committing, the same reasoning that already separated these two landing shapes in the prior slices.

**`SearchCombobox` is not used anywhere in this slice.** Confirmed deliberately, not by default: `SubjectOffering`'s own scale is bounded (three bounded, interrelated factors — a global Subject catalog, per-year Sections, per-year Terms — multiplying to a small real row count, not a genuinely unbounded population like Student/Enrollment), and the actual UX need is a three-facet structured pick, not free-text disambiguation among many similarly-named results. Building this slice around `SearchCombobox` merely because it was the most recently built primitive would have been the wrong tool chosen for familiarity rather than fit — the correct generalization from two prior slices is "pick the right shape for the actual anchor," not "reuse the newest component."

### 32.3 Backend: `SubjectOfferingController` — New, Minimal, Read-Only

```
GET /academic/subject-offerings?section_id=&term_id=&subject_id=   (all filters optional)
GET /academic/subject-offerings/{id}
```

Lives in `Academic` (Domain) — confirmed safe by §32.8, not the People-module placement `EnrollmentController` needed. Because `Subject`/`Section`/`Term`/`AcademicYear` are all Academic-internal, the response resolves **full display names directly** (`subject_name_en/ar`, `section_name`, `term_name_en/ar`, `academic_year_name_en/ar`) — a real, structural simplification over `EnrollmentController`'s own response (§31.2), which was forced to expose raw FK ids only, precisely because that controller could not reference Academic's models at all. This is the first tangible payoff of the anchor living Domain-side rather than Foundation-side.

**Deliberately not a full CRUD surface.** `SubjectOffering` creation remains backend-only, via the existing `CreateSubjectOfferingAction` (Phase 5 Sprint C) — no admin UI for creating offerings is built in this slice, matching Homeroom's own precedent of never building a CRUD surface for its own bounded anchor (`Section` already has one, from an earlier, unrelated sprint; `SubjectOffering` simply doesn't get one here either).

### 32.4 Route Structure — [Reused verbatim from §30.2/§31.4]

```
/w/assignments/teacher                                      → Landing (three cascading pickers)
/w/assignments/teacher/{subjectOfferingId}                   → Timeline
/w/assignments/teacher/{subjectOfferingId}/new                → Create
/w/assignments/teacher/{subjectOfferingId}/{id}/close          → Close
/w/assignments/teacher/{subjectOfferingId}/{id}/cancel          → Cancel
```

Same nested-splat dispatch shape; the `teacher` tab already exists as an unwired stub (§30.2) and is wired for real here.

### 32.5 Timeline Presentation — [Reused from §30.3/§31.5, one entity-specific slot filled the same way as Homeroom]

The shared `Timeline` component is reused verbatim, unmodified — no changes to `platform/timeline/*` for a third consecutive slice, the strongest evidence yet for §30.7's own claim.

Anchor context is shown once, at the page level (not per row), mirroring how `HomeroomTimelinePage`/`SectionTimelinePage` each show their own fixed anchor's identity: **title = the Subject's own name** (the single most identifying fact); **description line = "{Section name} · {Term name} · {Academic Year name}"**, all bilingual-resolved directly from `SubjectOfferingController`'s own response (§32.3) — no client-side id-to-name resolution needed here, unlike SectionAssignment's `academic_year_id`/`grade_level_id` (§31.2's own correction).

**`primaryLabel` per Timeline row = the assigned Employee's own name** — structurally identical to Homeroom's own row shape (§30.3), not SectionAssignment's (§31.5). This is a real, useful symmetry to name explicitly: Homeroom (anchor `Section`, fixed; row-level "other party" `Employee`, varying) and TeacherAssignment (anchor `SubjectOffering`, fixed; row-level "other party" `Employee`, varying) share the exact same shape — both assign a *person* to a *fixed thing* over time. SectionAssignment was the structural outlier of the three (anchor is the *person*, `Enrollment`; the row-level "other party" is the *thing*, `Section`).

### 32.6 Create / Close / Cancel Forms

**Create: reused shape from Homeroom exactly, not extended the way SectionAssignment's was.** `Employee` (`async-select`, reusing `loadEmployeeOptions` completely unchanged — confirmed capable of this without modification) + `effective_from` (date, defaulting today) — nothing else. `SubjectOffering` is fixed by the route, matching Homeroom's own Section-fixed-by-route shape exactly (the mirror image of SectionAssignment, where Enrollment was fixed by the route and Section was the real form field). **No client-side filtering of the Employee list is needed or added** — unlike SectionAssignment's own Create form (§31.6), which had to constrain its Section picker against the Enrollment's own Consistency Invariant, no equivalent invariant exists between `Employee` and `SubjectOffering`: any active employee can be assigned to teach any offering, a real, disclosed simplification versus the prior slice, not an oversight.

**Close/Cancel: reused verbatim from §30.4/§30.7/§31.6.** `CloseAssignmentForm`/`CancelAssignmentForm` called with `context = 'subject_teacher_assignment'` (the seeded reason codes: `teacher_reassigned`, `entered_in_error` — confirmed to match `TeacherAssignment::temporalReasonContext()`'s own return value exactly) and this slice's own copy — zero changes to the shared component files, the second consecutive proof (after SectionAssignment) that §30.7's "reused verbatim... only the `context` string changes" claim holds for a third consumer.

### 32.7 No Consistency Invariant to Duplicate — Simpler Than SectionAssignment, Not an Oversight

`TeacherAssignment` carries no `booted()`-level cross-model invariant of its own (unlike `SectionAssignment`'s branch/academic_year/grade_level three-way check) — it relies entirely on `HasTemporalAssignment`'s own `guardAgainstOverlap()` (one active teacher per `SubjectOffering` at a time, the trait's own documented v1 single-cardinality assumption, not a permanent constraint — `TeacherAssignment.php`'s own docblock already discloses this). §31.7's own rule (no business-rule duplication in the frontend) has nothing extra to apply here beyond what §30.4 already established for the overlap guard itself — this section exists to record that the simplicity is deliberate and verified, not an unexamined gap.

### 32.8 Backend Ownership & Layering — Re-Verified, Not Assumed

| File | Module | Status |
|---|---|---|
| `SubjectOfferingController` (`index`, `show`) | Academic (Domain) | New |
| `TeacherAssignmentController` (`index`/`store`/`close`/`cancel`, no `update`/`destroy`) | Academic (Domain) | New, mirrors `HomeroomAssignmentController`'s shape exactly (not `SectionAssignmentController`'s — the anchor/form-field relationship matches Homeroom, §32.6) |
| `AssignTeacherToSubjectOfferingAction` | Academic (Domain) | Modified — §32.10 |
| `TeacherAssignment::endedBy()` | Academic (Domain) | New — §32.10, same reasoning as the two prior slices' own copies |

Re-verified directly against `backend/deptrac.yaml` (not inferred from the pattern holding twice before): `Subject`, `Section`, `Term`, `AcademicYear`, `SubjectOffering`, and `TeacherAssignment` all share the `App\Modules\Academic\*` namespace — **Academic referencing Academic is not a cross-module dependency at all**, the `Domain: [Core, Foundation, Administration]` ruleset's restriction on Domain-to-sibling-Domain access (e.g. Academic depending on Admissions) simply does not apply within one module. `Section.php`'s own docblock independently corroborates this: *"its own relations to them are Domain → Domain (unrestricted under the current deptrac collector)."* `TeacherAssignmentController`'s own references to `Employee`/`Person` (People, Foundation) and, via `endedBy()`, `User` (Identity, Foundation) are both the same permitted Domain→Foundation direction `HomeroomAssignmentController`/`SectionAssignmentController` already use safely. No new dependency direction, no boundary risk — confirmed, not assumed, precisely because this freeze's own research was instructed to actively hunt for a repeat of SectionAssignment's own mistake rather than trust that "everything's in Academic" was automatically safe.

### 32.9 Permissions

`academic.manage-catalog` — no new permission, for both new controllers, matching every prior Assignment-engine controller's own precedent exactly.

### 32.10 Known Pre-Existing Gaps Closed As Part Of This Slice

1. **`AssignTeacherToSubjectOfferingAction::execute()`** hardcodes `'effective_from' => now()` and `'status' => 'active'` unconditionally — the third occurrence of the identical defect §30.9 and §31.11 each already found and fixed in this Action's own siblings. Fixed here identically: an optional `Carbon|string|null $effectiveFrom` parameter, `status` computed from the resolved date (`'scheduled'` when after today, `'active'` otherwise), before this slice's own Create form (which makes `effective_from` a real, user-editable field per §32.6) goes live.
2. **`TeacherAssignment::endedBy(): BelongsTo` → `App\Modules\Identity\Models\User`** does not exist yet, despite the `ended_by_id` column already existing on `teacher_assignments` and already being populated by `closeAssignment()`/`cancelAssignment()`. Added here, on the model itself (never the shared `HasTemporalAssignment` trait — Core must never depend on a Foundation module), identical to `HomeroomAssignment::endedBy()`/`SectionAssignment::endedBy()`.

### 32.11 Out of Scope

- Any admin UI for creating/editing `SubjectOffering` itself — stays backend-only (`CreateSubjectOfferingAction`).
- Curriculum/grade-level matching (the SubjectOffering model's own documented, deliberately unenforced 4th invariant) — remains deferred, unrelated to this slice.
- Any change to `platform/timeline/*`, `SearchComboboxField`, `command.tsx`, or any other shared primitive — this slice proves they generalize a second/third time by reuse, not by modification. No "generalize the pattern further" work is undertaken speculatively here.
- A dedicated `cancelAssignment()` test for the `TeacherAssignment` *model* (`TeacherAssignmentTest.php` currently has none, unlike `closeAssignment()`) — not fixed as a separate task; naturally covered by the new `TeacherAssignmentController` test suite's own cancel-flow test, the same way `store`/`close`/`cancel` coverage arrives via the controller tests in both prior slices, not via retroactively patching an unrelated pre-existing model-level test file.

### 32.12 Migration / Schema Changes — None

`teacher_assignments` already carries every column this slice needs (`employee_id`, `subject_offering_id`, `effective_from`, `effective_until`, `status`, `reason_code_id`, `ended_by_id`). `subject_offerings` already carries everything `SubjectOfferingController` needs. No new column, no new index — confirmed by direct inspection of both tables' migrations, not assumed from the two prior slices' own zero-migration outcome.

### 32.13 Reused / Extended / New — Summary

| Piece | Status |
|---|---|
| `Timeline`, its four Badges, `asOf(today)` logic | Reused verbatim (third consecutive slice, zero `platform/timeline/*` changes) |
| `CloseAssignmentForm`/`CancelAssignmentForm` | Reused verbatim (only `context` + copy change) |
| Route/breadcrumb nested-splat shape, Tab Switcher | Reused verbatim |
| `{message, errors}` 422 convention | Reused, `employee_id` attachment (matches Homeroom, not SectionAssignment) |
| Create form shape (Employee async-select + effective_from) | Reused verbatim from Homeroom, unconstrained (simpler than SectionAssignment) |
| `loadEmployeeOptions`, `loadReasonCodeOptions`, `sectionProvider`, `termProvider`, `loadOpenAcademicYear()` | Reused, zero modification |
| `primaryLabel` = assigned person's name | Reused pattern from Homeroom (structural sibling, not SectionAssignment's) |
| `SearchComboboxField`/`command.tsx` | **Not used in this slice** — confirmed deliberately unfit for a bounded, composite anchor |
| Landing page (cascading Section→Term→derived-SubjectOffering pickers) | New pattern shape (neither Homeroom's single bounded pick nor SectionAssignment's live search) |
| `SubjectOfferingController` | New |
| `TeacherAssignmentController` | New |
| `AssignTeacherToSubjectOfferingAction` fix, `TeacherAssignment::endedBy()` | New fix, closing a pre-existing gap (§32.10) |

**Design Freeze: APPROVED (2026-08-12).** All ten implementation questions answered and confirmed, including the landing-page shape correction (cascading bounded pickers, not `SearchCombobox`) and the re-verified deptrac boundary (zero conflicts, confirmed directly rather than assumed from precedent). Implementation begins next, in this order: `AssignTeacherToSubjectOfferingAction` fix → `TeacherAssignment::endedBy()` → `SubjectOfferingController` → `TeacherAssignmentController` → Landing → Timeline → Create → Close → Cancel — live verification, full backend/frontend verification suite, then an independent read-only repository review and a fix cycle if needed, before this section is updated to reflect closure, exactly the cycle already followed twice for Homeroom and SectionAssignment. No implementation code is written before this section's own status is updated to reflect the freeze being approved — which it now is.

### 32.15 Implementation: Backend

`AssignTeacherToSubjectOfferingAction::execute()` gained the same fix already applied twice to its siblings (`AssignHomeroomTeacherAction`, §30.9/§30.15; `AssignSectionAction`, §31.11): an optional `Carbon|string|null $effectiveFrom` parameter, with `status` computed from the resolved date (`'scheduled'` when after today, `'active'` otherwise) rather than hardcoded to `now()`/`'active'` unconditionally. `TeacherAssignment::endedBy(): BelongsTo` → `App\Modules\Identity\Models\User` was added directly on the model, not the shared `HasTemporalAssignment` trait — identical reasoning to `HomeroomAssignment::endedBy()`/`SectionAssignment::endedBy()` (Core must never depend on a Foundation module; Academic depending on Identity is the permitted direction).

`app/Modules/Academic/Http/Controllers/SubjectOfferingController.php` (new) — `index()` (optional `section_id`/`term_id`/`subject_id` filters) and `show()`, both read-only, both gated by `academic.manage-catalog`. Returns fully resolved bilingual names (`subject_name_en/ar`, `section_name`, `term_name_en/ar`, `academic_year_name_en/ar`) alongside every raw FK, deliberately unlike `EnrollmentSchema`'s raw-id-only approach (§31.2) — SubjectOffering's own relation chain lives entirely inside Academic, so resolving names here crosses no module boundary, and the frontend's Landing/Timeline pages need the resolved names directly (§32.2/§32.5).

`app/Modules/Academic/Http/Controllers/TeacherAssignmentController.php` (new) — `index`/`store`/`close`/`cancel`, no `update`/`destroy`, mirroring `HomeroomAssignmentController`'s shape exactly (not `SectionAssignmentController`'s) — the anchor (`SubjectOffering`, fixed by the route) and the form field (`Employee`) match Homeroom's own Section-anchor/Employee-field relationship, not SectionAssignment's inverted one. `store()`'s failures attach to `employee_id`, matching Homeroom. `close()`'s classification (reason-related exception text → `reason_code`; everything else → `effective_until`) reuses the same approach established in §30.9, unchanged.

Both controllers were added to `routes/api.php` inside the existing `academic` prefix group: `GET /subject-offerings`, `GET /subject-offerings/{id}`, `GET /teacher-assignments`, `POST /teacher-assignments`, `PATCH /teacher-assignments/{id}/close`, `PATCH /teacher-assignments/{id}/cancel`.

### 32.16 Implementation: Frontend

`packages/contracts/src/assignments/assignments.schemas.ts` gained `SubjectOfferingSchema` (fully resolved names, per §32.15's own reasoning — contrasted in-line against `EnrollmentSchema`'s raw-id-only approach) and `TeacherAssignmentSchema` (mirrors `HomeroomAssignmentSchema` exactly).

`admin/src/workspaces/assignments/providers/` gained `subject-offering-provider.ts` (`listSubjectOfferings(filters)`/`getSubjectOffering(id)`) and `teacher-assignment-provider.ts` (`listForSubjectOffering`/`create`/`close`/`cancel`, mirroring `homeroomAssignmentProvider`/`sectionAssignmentProvider` exactly).

`admin/src/workspaces/assignments/teacher/` (new) gained five pages and `constants.ts` (`TEACHER_REASON_CONTEXT`). The Landing page (`teacher-landing-page.tsx`) implements the frozen cascading shape exactly: `AsyncSelectField` for Section and Term (reusing `sectionProvider`/`termProvider`/`loadOpenAcademicYear()` verbatim, no new endpoint), a third `AsyncSelectField` whose `loadOptions` calls `listSubjectOfferings({sectionId, termId})` and maps each real `SubjectOffering` to `{value: String(offering.id), label: subjectName}` — the option's value is `subject_offering_id` directly, never a composite or a raw `subject_id`. A distinct empty-state hint (`subjectHintIncomplete` vs `subjectHintEmpty`) is rendered from a second `useQuery` sharing the exact same `queryKey` `AsyncSelectField` already uses internally (so React Query dedups the fetch) rather than passing an unsupported `placeholder` prop into the shared component — `AsyncSelectField` itself was not modified. The `View timeline` button is an explicit, disabled-until-selected submit; there is no auto-navigate on selection. `teacher-timeline-page.tsx` resolves title (Subject name), description (`[section_name, termName, academicYearName].filter(Boolean).join(' · ')`), and each row's `primaryLabel` (employee name) directly from `SubjectOfferingController`'s own resolved-name response — no client-side id-to-name resolution needed, unlike SectionAssignment's own Enrollment. `teacher-create-page.tsx` is Employee (`AsyncSelectField`, unconstrained, reusing `loadEmployeeOptions` verbatim — no client-side filtering the way SectionAssignment's Section field needed, §32.7) + `effective_from` only. `teacher-close-page.tsx`/`teacher-cancel-page.tsx` are thin wrappers around `CloseAssignmentForm`/`CancelAssignmentForm`, reused verbatim.

`assignments-workspace-page.tsx`'s previously-unwired `teacher` tab stub was replaced with a `TeacherRoutes` splat-dispatch function, structurally identical to `HomeroomRoutes`/`SectionRoutes`. `locales/{en,ar}.json` gained matching `teacher.*` key sets (27 keys each, parity confirmed).

### 32.17 Issues Caught During Implementation (Before Any Verification Was Run)

Two mistakes were made and self-caught while writing `teacher-landing-page.tsx`, before any build, lint, or test command was run against the new code — worth recording distinctly from "bugs found live" (§32.18 found none) because they show the mistake being caught by re-reading `AsyncSelectField`'s actual type signature rather than by a failing build:

1. An initial draft passed an unsupported `placeholder` prop into `AsyncSelectField` (whose actual signature, confirmed by re-reading `platform/forms/async-select-field.tsx`, is `{control, name, label, loadOptions, queryKey}` only — it hardcodes its own `isLoading ? 'Loading…' : undefined` internally and accepts no external placeholder). Per §32's own "no modification to shared components" constraint, the fix was not to extend the shared component but to compute the same "why is this empty" signal from a second `useQuery` in the Landing page itself, sharing the exact `queryKey` `AsyncSelectField` already uses (§32.16).
2. An intermediate edit referenced a `SubjectOfferingEmptyHint` component that was never defined — caught in the same turn, before compiling, and replaced with the inline hint JSX described in §32.16.

### 32.18 Live Verification

Docker backend, real login as `testuser`, real dev database. The dev database's only `status='active'` Academic Year at verification time (id 96, "2025-2026") had a Section but no Term of its own, so a temporary Term (`ZZ_TeacherAssignVerify Term`, `academic_year_id=96`), a `SubjectOffering` combining that Section + Term + the existing `Subject` "Mathematics", and one `Employee` were created via `php artisan tinker` as a Fixture Revert-discipline fixture. Full flow exercised live end-to-end in both languages: Landing's three cascading pickers (Section → Term → derived Subject Offering, confirming the third dropdown lists only the one real `SubjectOffering` and nothing else) → explicit `View timeline` → empty Timeline (title = "Mathematics", description = "h · ZZ_TeacherAssignVerify Term · 2025-2026") → Assign (Employee + `effective_from` = today) → Timeline row correctly `Active`, `primaryLabel` = employee's name, range "→ Ongoing" → Close (reason + future `effective_until`, confirm-gated) → row correctly `Ended`, reason and `Ended by` shown → a second Assign with a future-dated `effective_from`, live-proving the §32.10/§32.15 bug fix by observing the new row render `Scheduled` (not `Active`) → Cancel on that scheduled row (confirm-gated, no `effective_until` field) → row correctly `Cancelled`, struck-through reason shown. Re-verified in Arabic after switching languages mid-session — Landing labels, hint text, subject/section/term names, status labels, and reason codes all correctly localized. Zero issues found live (contrast with §31.18/§30's own slices, each of which found at least one live bug — this is the first of the three slices where the design-freeze-to-implementation path produced none). The entire fixture (2 `TeacherAssignment` rows, 1 `SubjectOffering`, 1 `Term`, 1 `Employee`, 1 `Person`) was force-deleted afterward in FK-safe order, confirmed via a final zero-row query against every fixture id created.

### 32.19 Backend Test Coverage

`tests/Feature/Academic/AssignTeacherToSubjectOfferingActionTest.php` (5 tests total — 2 pre-existing plus 3 new: caller-specified `effective_from` honored, future-dated assignment marked `scheduled`, same-day assignment still marked `active`), `tests/Feature/Academic/SubjectOfferingControllerTest.php` (7 tests — filtered/unfiltered `index()`, `show()`, permission denial, super-admin bypass), `tests/Feature/Academic/TeacherAssignmentControllerTest.php` (11 tests — timeline ordering, create success with the `effective_from` round-trip, closed-year rejection, overlap rejection, close success with `ended_by` resolution, same-day-close rejection attached to `effective_until` not `reason_code`, unregistered-reason rejection, cancel success, permission denial, no update/delete route).

### 32.20 Verification Results

Backend: `php artisan test` **577/577 passing** (21 new since §31's 556 baseline: 3 + 7 + 11), `./vendor/bin/pint --test` clean (431 files), `./vendor/bin/deptrac analyse --no-progress` **0 violations** (confirming Academic→Academic is genuinely unrestricted — no layering conflict exists in this slice, per §32.1/§32.8's own claim). `php artisan migrate:status` — nothing pending, confirming §32.12's zero-schema-change claim. Frontend: `tsc -b` clean, `oxlint .` clean (the same two pre-existing fast-refresh warnings noted in every prior slice, none new), `vitest run` 4/4, `vite build` succeeded.

### 32.21 Documentation-First Compliance Note and Independent Read-Only Repository Review

Unlike the first three consecutive slices (§29.7, §30.15, §31.22 — each of which shipped a first pass that implemented, live-verified, and believed itself closed without updating this document, `docs/IMPLEMENTATION_PLAYBOOK.md`, or `CHANGELOG.md`, and each of which needed a fresh reviewer to catch it), this section, §32.22 below, the `IMPLEMENTATION_PLAYBOOK.md` Sprint 2 status row, and the `CHANGELOG.md` entry were written as part of the same task before the phase was declared closed — deliberately breaking the pattern rather than repeating it a fourth time.

A fresh independent agent, no prior context, re-derived every substantive §32 claim from source and from re-running every command itself rather than trusting this document's prose: re-read `AssignTeacherToSubjectOfferingAction.php` and its tests directly to confirm the `effective_from`/`status` fix is real; re-read `TeacherAssignment.php` to confirm `endedBy()` is correctly placed; diffed all three Assignment controllers directly to independently confirm `TeacherAssignmentController` mirrors `HomeroomAssignmentController`'s shape and not `SectionAssignmentController`'s (rather than accepting the claim on the doc's word); re-ran `php artisan test`/Pint/deptrac/`migrate:status` fresh; read all five new frontend pages and both new providers to confirm the third Landing dropdown genuinely derives only real `SubjectOffering`s and that its value is `subject_offering_id` directly; confirmed `SearchComboboxField` is genuinely absent from this slice; confirmed Close/Cancel call the same shared `CloseAssignmentForm`/`CancelAssignmentForm` used by the two prior slices, unmodified; grepped every `t('teacher.*')` call across the five pages against both locale files and found full parity; re-ran `tsc -b`/`oxlint`/`vitest run`/`vite build` fresh; cross-checked `SubjectOfferingSchema`/`TeacherAssignmentSchema` field names against both controllers' own `transform()` methods; and grepped `admin/src/platform/` for any TeacherAssignment-specific modification, finding none beyond forward-looking doc comments. **Verdict: PASS — §32 is accurately and correctly implemented, no discrepancies found**, with one non-blocking observation (a large body of unrelated, pre-existing uncommitted business-documentation work already present in the working tree from a prior, unrelated task, unrelated to this slice's own scope and not introduced by it — noted here only so a future commit step separates it rather than sweeping it in unintentionally).

Following this documentation update, the slice is considered **closed on the same evidentiary bar applied to every prior phase in this project** — verified by re-derivation, not self-report, with zero findings requiring a fix cycle.

### 32.22 Status

**UI Sprint 2, TeacherAssignment vertical slice: COMPLETE AND CLOSED.** This is the third and final vertical slice of the Temporal Assignment Workspace pattern. The pattern has now been proven across three structurally different anchors: a small bounded picker (Homeroom's own Section), a genuinely unbounded live search (SectionAssignment's own Enrollment/Student), and a bounded composite derived from cascading pickers (this slice's own SubjectOffering) — with `Timeline`, `CloseAssignmentForm`/`CancelAssignmentForm`, the Tab Switcher, and the nested-splat route/breadcrumb shape reused verbatim across all three without a single modification. UI Sprint 2 is complete; per `docs/IMPLEMENTATION_PLAYBOOK.md`'s own roadmap, the next planning conversation is not a continuation of this pattern but a fresh Architecture/UX Pass for whatever comes after (e.g. Timetables/Attendance), not assumed here.
