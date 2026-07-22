# Domain 12: Inventory

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v3 · **Related ADRs:** [BUS-0008](../adr/business/0008-inventory-classification-business-purpose.md), [BUS-0009](../adr/business/0009-tracking-strategy-setting-not-classification.md), [BUS-0010](../adr/business/0010-stock-movement-journal-entry-equivalent.md) · **Related Domains:** [HR](hr.md), [Students](students.md), [Accounting](accounting.md), Procurement (future), [Health Clinic](health-clinic.md), [School Operations](school-operations.md), Facilities (future), Assets (future)

**Documentation status, added 2026-07-22.** Every decision below was fully worked out in this domain's own prose across three design turns, but until now had no corresponding ADR in `docs/adr/business/` — the content existed, the governance artifact didn't. Now formalized: the classification test and its two revisions (BUS-0008), Tracking Strategy as a Setting with an Item Catalog override (BUS-0009), and Stock Movement as this domain's Journal-Entry equivalent (BUS-0010). Unlike Learning, this domain's own body text already reflects all three decisions accurately — the gap here was the ADR, not the prose.

**Domain vs. Module reconciliation**: no independent enrollment — Module-shaped on the Program axis, same as every OT/operational domain so far. Full Domain on the bounded-context axis: its own master data (item catalog, stock locations), three genuinely distinct sub-lifecycles, and cross-cutting integration with nearly every other domain in this document (HR, Students, Accounting, Procurement, Health Clinic, School Operations, Facilities, Assets). Worth stating explicitly: **this is not an OT domain** — it issues no commands to physical hardware and has no life-safety dimension, so the command-priority and offline-fail-safe obligations that apply to School Operations and Smart Campus don't apply here. It's an ordinary IT domain with unusually wide cross-domain reach.

### Purpose
Govern the full lifecycle of every physical item the institution owns or holds — from receipt through issue, use, and eventual return, consumption, or disposal — organized around how the institution is actually accountable for each item, not around where it happens to be stored.

### Classification test (revised a second time — see the revision note below)

The brief's own closing instruction — "don't introduce a new top-level category unless there's a fundamentally different business lifecycle" — needs a checkable test to actually hold five years and several item types from now, the same way every other classification rule in this document has one.

1. **Is the item expected to be returned?**
   - No → continue to step 3.
   - Yes → continue to step 2.
2. **(returnable items only) What is the item's fundamental business purpose — is it provided so a student can *learn with it* (an educational material), or is it equipment/property the institution is entrusting to someone and holding them *accountable for its use*?**
   - Educational material, recipient is a student → **Student Inventory**, as the *Annual Reusable Issue* workflow (see Workflows below), synchronized with the Academic Year cycle.
   - Accountable equipment/property, regardless of recipient — student or employee → **Custody Inventory** — the same category covers an employee's laptop and a student's lab equipment, because the *lifecycle* (assign, transfer, return, inspect, close) is identical; only the holder differs.
3. **(non-returnable items only) Does the same physical unit remain usable after issue, indefinitely — i.e., does it survive being "used"?**
   - Yes → **Student Inventory**, as the *Permanent Issue* workflow (a uniform, a student ID is *for* the student's use/identity, not equipment the institution is holding them accountable for).
   - No (consumed by the act of using it) → **Consumable Inventory**.

**Revision note.** This test has now been corrected twice, and both corrections are worth being honest about rather than smoothing over. First draft: "is it returned? → Custody," which misclassified a returned textbook. First correction: "is accountability individual-unit or pool-level? → that decides Custody vs. Student Inventory," which turns out to be wrong for a subtler reason — tracking granularity is a **school-configurable enforcement policy**, not a fixed property of an item. A school can choose to serialize and individually track every textbook, or not; either way, a textbook's *purpose* is unchanged, and it stays Student Inventory regardless of that choice. Individual-vs-pool tracking happened to correlate with the right answer for the common cases (laptops are almost always serialized, textbooks are usually pooled) — which is exactly why it looked like a valid test and wasn't caught immediately — but a correlation isn't a cause, and the classification test needs the cause. **Business purpose is stable across every school; tracking granularity varies by school and belongs in configuration, not classification** — see Tracking Strategy under Master Data and Settings below.

Still exhaustive by construction: {non-returnable: survives use / consumed by use} × {returnable: educational-material / accountable-property}. **Who administratively manages an item — Student Services versus Custody Management — is a consequence of running this test, never an input to it.** Org charts change; an item's business purpose doesn't.

### Responsibilities
Student Inventory (permanent issue), Custody Inventory (temporary, accountable issue), Consumable Inventory (used-up stock) — all sharing one physical-location infrastructure layer.

### Business Capabilities
Receive stock into a location · issue an item permanently to a student (or, per the test above, an employee) with billing handoff where applicable · assign, transfer, and reclaim custody of accountable equipment with condition tracking · manage the request-approve-issue cycle for consumable stock · track damage, loss, and replacement for custody items · monitor stock levels and trigger replenishment · check Inventory Availability before committing any Reservation or Allocation, rather than discovering a shortfall at Issue time · report on issued/held/consumed items by person, department, category, or location.

### Submodules
Student Inventory · Custody Inventory · Consumable Inventory · Stock Management (the shared ledger engine beneath all three, detailed in its own section below — infrastructure, not a fourth category, same status as Stock Locations)

### Stock Management — the shared ledger layer

**Verdict on the question this was built to answer: yes, Stock Movement plays exactly the architectural role inside Inventory that Journal Entry plays inside Accounting — not by resemblance, but by the same three structural properties.** In Accounting, no workflow (Invoice Payment, Payroll Run) ever writes a balance directly; every balance-affecting event creates a Journal Entry, and the balance is a computed aggregate of entries, never an independently-editable field. The identical shape applies here: **no business workflow (Issue, Return, Consumption, Receipt) ever writes a stock quantity directly — every one of them creates a Stock Movement, and every quantity is a computed aggregate of movements.** This is the binding rule for the domain, not a suggestion:

> **Business workflows describe *why* inventory changed. Stock Movement records *how* it changed. Business workflows must never modify stock directly — every change happens through a Stock Movement, so a complete, immutable ledger exists.**

**Stock Movement** — the atomic transaction record every workflow produces: Item, Location, Quantity, Movement Type, a reference back to whichever business record caused it, timestamp, actor. That last part completes the analogy precisely — a Journal Entry carries a reference back to its source document (an Invoice, a Payroll Run) without needing to understand invoicing or payroll; a Stock Movement carries a reference back to its source (a Custody Agreement, a Consumable Request, a Student Issue) without Stock Management needing to understand any of the three domains above it. Movement Type is one shared vocabulary across all three lifecycle categories, exactly as specified:

| Workflow step | Movement Type |
|---|---|
| Student Issue | `ISSUE` |
| Student Return | `RETURN` |
| Custody Assignment | `ISSUE` |
| Custody Return | `RETURN` |
| Consumable Issue | `CONSUME` |
| Supplier Receipt | `RECEIVE` |
| Warehouse Transfer | `TRANSFER` |
| Inventory Count variance | `ADJUSTMENT` |
| Damage | `DAMAGE` |
| Loss | `LOSS` |
| Write-off | `WRITE_OFF` |

**Stock Ledger** — not a separately-maintained table, the same way a General Ledger isn't separate from its Journal Entries: it *is* the append-only sequence of Stock Movements for an item/location, queryable and replayable to reconstruct a balance as of any past date.

**Stock Balance** — seven components, all derived, none directly writable, and related to each other precisely rather than just listed: **Current** is the total physical count regardless of status, and the other six are a mutually exclusive partition of it — **Available** (Current minus everything below), **Reserved** (soft-held for a pending, approved-but-not-yet-issued request — a Consumable Request between Approval and Issue, for instance), **In Transit** (mid-Transfer between two Locations), **On Hold** (blocked pending an assessment, decision not yet made), **Damaged** (assessment already completed, confirmed damaged, awaiting Repair/Replacement/Write-off — a distinct, later state than On Hold, not a duplicate of it), **Expired** (past its expiration date per Expiration Management, automatically moved here by the Expiration Review process rather than by a human decision). **Allocation** is the step between Reservation and full Issue already named in Student Inventory's Permanent Issue workflow ("Allocate to Student") — it earmarks a specific unit for a specific recipient without yet being the Movement that actually transfers it.

**Inventory Availability** is a capability, not an eighth stored field — a query ("can this request for N units of Item X at Location L be satisfied, now or by date D") that every workflow consults *before* creating a Reservation or Allocation, computed from Available Quantity but not the same thing as it: Available Quantity is the number, Availability is the check a workflow gates on before it's allowed to proceed.

**Inventory Snapshot** — a periodic, cached checkpoint of Stock Balance at a point in time, the same role a closed accounting period's carried-forward opening balance plays: reconstructing a balance as of an arbitrary past date shouldn't require replaying the entire Ledger from system inception, only the movements since the nearest Snapshot. A Physical Inventory Count is naturally also a verified Snapshot — the two are the same event, one operational (counting), one structural (checkpointing the Ledger).

**Batch/Lot Management, Serial Number Management, Expiration Management** — these aren't separate concepts, they're the concrete mechanism that *realizes* Tracking Strategy (established two revisions ago): an item whose Tracking Strategy is individually-serialized requires a Serial Number on every Movement touching it; a pool-tracked item doesn't. Batch/Lot and Expiration apply orthogonally, mainly to Consumables (a chemical lot with a shared expiry date across many units).

**Inventory Valuation** — computed from the Ledger (quantity × cost basis, by whichever valuation method the deployment configures — FIFO, weighted-average, standard cost), and this is where Stock Management's Accounting analogy stops being structural-only and becomes a genuine integration point: for valued stock, a `RECEIVE` Movement can generate a corresponding Accounting Journal Entry (inventory asset value increase), and a `CONSUME`/`WRITE_OFF` Movement can generate one recognizing the expense. Two domain-specific ledgers, not merged, connected by events — the same discipline already used for Emergency Coordination and LMS-to-Academic gradebook sync.

**Inventory Assessment** — a periodic review category, distinct from day-to-day transactional workflows: **Physical Inventory Count** (full count at a location) and **Cycle Count** (a rolling partial count) both feed **Reconciliation**, which compares the counted physical quantity against the Ledger-derived Balance and produces an `ADJUSTMENT` Movement for any variance — this is the precise mechanism behind the reconciliation note above (pool-tracked variance is detected by aggregate mismatch, serialized variance identifies the specific missing unit). **Damage Assessment** and **Condition Assessment** are the same review applied at Return/Inspection rather than on a schedule. **Obsolete Stock Review**, **Slow Moving Review**, **Fast Moving Review**, and **Expiration Review** are the periodic processes that produce the reports already listed below (fast/slow-moving items, expiring items) — the reports were always the *output*; this section is what actually generates them.

### Master Data
**Item Catalog** (a catalog entry — "Grade 5 Uniform Size M," "Dell Latitude Model X," "A4 Paper Ream" — referenced by every stock, issue, and custody record; carries which of the three lifecycle categories it belongs to, its Valuation Method if valued, and, for any returnable item, its **Tracking Strategy** — pool-level or individually-serialized — as an override on the deployment-wide default set in Settings below) · **Item Category** (a filterable sub-classification within each lifecycle type — Uniforms/Textbooks within Student Inventory, Electronics/Lab Equipment within Custody — a secondary axis, not a competing top-level one) · **Stock Location** (Warehouse, Room, Shelf, Bin — referenced by every physical unit's current location, shared identically across all three lifecycle categories, exactly as specified: infrastructure, not classification).

Deliberately **not** Master Data: Custody Agreement, Consumable Request, Issue records, **and Stock Movement itself** — all transactional/ledger records with their own lifecycle, the same distinction already drawn elsewhere in this document between reference data (Academic Year, Grade) and period-scoped aggregates (Enrollment, Employment). Stock Balance is doubly not Master Data — it's not even independently stored truth, it's a cached, always-recomputable aggregate of Stock Movements, the same relationship an Account Balance has to Journal Entries in Accounting.

### Settings
**Tracking Strategy default** — pool-level or individually-serialized, set per lifecycle category (e.g., default individually-serialized for Custody, default pool-level for Annual Reusable Issue), resolved the same way any other Domain Configuration resolves, with the Item Catalog's own field (above) as the per-item override — the identical default-then-override shape `SettingsResolver`'s altitude chain already uses for org/branch, just applied at item-type granularity instead. This is the concrete answer to where Tracking Strategy lives: **not** a classification input, a Setting with an Item Catalog override. Student Inventory — Permanent Issue: standard issue kit per Grade/Program. Student Inventory — Annual Reusable Issue: return deadline relative to Academic Year end, reissue eligibility condition threshold, retirement/wear threshold. Custody: default return period per Item Category, overdue-reminder schedule. Consumable: low-stock threshold and reorder point per item, approval-required threshold by request value or department.

### Workflows
Every step below that touches a quantity produces a Stock Movement (see Stock Management above) — that's now the governing rule for all three lifecycle categories, not a detail of any one of them.

**Student Inventory** has two workflows, not two submodules — see the note at the end of this section for why. **Permanent Issue** — Receive Stock (`RECEIVE`) → Allocate to Student → Issue (`ISSUE`) → History (uniforms, ID cards, graduation kits — no return expected). **Annual Reusable Issue** — Receive (`RECEIVE`) → Issue (`ISSUE`) → Return (`RETURN`) → Inspection → Reissue (`ISSUE`) → Retire (`WRITE_OFF`) (textbooks, library-owned classroom materials — synchronized with Academic's own Academic Year rollover, [Academic](academic.md), rather than triggered ad hoc). **Custody Inventory** — Receive (`RECEIVE`) → Assign Custody (`ISSUE`) → Transfer Custody (`TRANSFER`) → Return (`RETURN`) → Inspection → Close, with Return Condition, Damage Assessment (`DAMAGE`), Replacement, Lost Item (`LOSS`), and Repair modeled as branches/sub-states of Inspection, not separate workflows of their own. **Consumable Inventory** — Receive (`RECEIVE`) → Store → Request → Approval → Issue (`CONSUME`) → Consumption.

**Why two workflows inside Student Inventory rather than a separate submodule**: the same test already applied elsewhere in this domain — do master data, permissions, and reporting genuinely diverge, or only the workflow steps? Permanent Issue and Annual Reusable Issue share the same Item Catalog, the same Stock Location infrastructure, the same recipient type (Student), and the same owning permissions — they diverge only in workflow shape, exactly the same relationship Custody's own Return Condition/Damage Assessment/Replacement/Lost/Repair branches already have to Custody's single workflow. A submodule is warranted when master data or permissions actually split; a workflow variant is warranted when only the steps differ. This is the latter.

### Domain Events
Two tiers, matching the why/how split Stock Management is built on. **Business-intent events** (why something happened): `ItemIssued` · `ItemReturned` · `ItemReissued` · `ItemRetired` · `CustodyOverdue` · `DamageReported` · `ItemLost` · `ConsumableRequested` · `ConsumableApproved` · `StockLevelLow` · `StockExpiring`. **Ledger-fact events** (how stock actually changed, one level below the business events — the Movement-level layer other domains or dashboards can subscribe to without needing to understand any of the three business workflows): `StockMovementRecorded` · `StockAdjustmentRecorded` · `ReconciliationVarianceFound` · `PhysicalCountCompleted`.

### Automation Opportunities
Auto-flag overdue custody returns from the configured return period · auto-generate a replenishment request the moment stock crosses the reorder point, feeding directly into Procurement's requisition workflow rather than requiring a manual step · auto-alert on consumables approaching expiry (medical supplies, lab chemicals) · auto-calculate a billable fee when a Student Inventory issue has a cost component, feeding Accounting · auto-generate the corresponding Accounting Journal Entry from a valued item's `RECEIVE`/`CONSUME`/`WRITE_OFF` Movement, rather than a manual finance step · auto-reconcile Physical/Cycle Count results against the Ledger-derived Balance and raise an `ADJUSTMENT` Movement for any variance — pool-tracked items reconcile by aggregate count (a mismatch signals *something's* missing, not *what*); individually-serialized items reconcile by exact unit match, identifying the specific missing item immediately rather than inferring it from an aggregate shortfall.

### AI Opportunities
Demand forecasting for consumables from consumption trend, to time reorder before a stockout rather than after · anomaly detection on custody loss/damage rates (a Branch losing equipment at a rate far above its peers is worth a human look) · photo-based damage-assessment assistance at Return/Inspection — a suggested condition rating from an uploaded photo, always human-confirmed before it affects billing for a Replacement.

### Provider Slots
**Barcode/RFID Scanner Provider** — the one legitimate hardware-adjacent slot in this domain, and deliberately lighter-weight than the OT domains' Provider Slots: this is an *input* device (reading an identifier), not an *actuator* commanding physical action, so it carries none of School Operations'/Smart Campus's command-priority or offline-fail-safe obligations.

### Public APIs
An item-status lookup (is this specific unit currently issued, to whom, in what condition) for other domains that need to know without needing inventory-management access · an Availability check other domains can call before assuming a request is satisfiable · a consumption feed for Accounting (billable Student Inventory issues, valued Movement journal entries) and Procurement (replenishment triggers).

### Extension Points
New Item Categories within any of the three lifecycle types, added as data · new Stock Locations as the physical footprint changes · a genuinely new top-level category is only ever justified by failing the two-question classification test above, never by a new item merely looking different from existing ones.

### Mobile Features
**Student & Parent App**: own issued Student Inventory items, own active Custody items with return due dates. **Employee App**: own active Custody items, submit consumable requests, approve requests where authorized.

### Dashboards
Stock levels by location and category · overdue custody items · low-stock and expiring-stock alerts.

### Reports
**Student Inventory**: issued items per student · missing textbook report · uniform distribution · student issue history · textbook return/reissue rate and retirement-due report (Annual Reusable Issue specifically). **Custody**: active custodies · overdue returns · damaged equipment · lost equipment · employee and student custody history. **Consumables**: consumption by department · monthly consumption · low stock · fast/slow-moving items · expiring items.

### KPIs
Custody return-on-time rate · damage/loss rate · consumable stockout frequency · consumption-forecast accuracy · **Inventory Accuracy** — the percentage of counted items matching their Ledger-derived Balance without requiring an Adjustment, the direct measure of how much the Ledger can be trusted as a substitute for physically counting stock.

### Security Classification
**Internal** — a deliberate contrast with Health Clinic and Smart Campus's Highly Sensitive tier: inventory data isn't personally sensitive at rest. Custody records do tie a specific item to a specific Person, which is a mild privacy dimension worth naming, but "who currently holds a laptop" is nowhere near the protection bar biometric or medical data requires — classifying it any higher would blur a distinction this document has otherwise been careful to keep sharp.

### Permissions
- **Inventory Manager** — full, including catalog and location management.
- **Warehouse/Store Staff** — receive/issue operations, no catalog or policy configuration.
- **Department Head** — request and approve consumables for their own department only.
- **Employee / Student** — self-service: view own issued and custody items, submit consumable requests where authorized.

### Audit Requirements
Full audit on issue, return, write-off, and loss events — accountability- and financial-significance driven, not the read-everything-is-audited standard Health Clinic and Smart Campus carry, since this data isn't personally sensitive at rest; the audit obligation here is about tracking accountability for physical/financial assets, not protecting private information.

### Data Ownership
Owns Item Catalog, Stock, and Custody/Issue records outright. Consumes Person identity from People, Procurement's PO/receipt data to replenish stock, and feeds Accounting for billable issues. A custody item that is also a tracked fixed asset (a laptop, for instance) is a **cross-reference, not a merge**, with the future Assets domain — Assets owns the item's financial lifecycle (purchase value, depreciation, disposal), Inventory owns its physical possession/accountability lifecycle (who holds it, its condition, its return status); the same physical unit, two separate aggregates, related by a shared identifier, exactly the owner/consumer discipline already established throughout this document rather than one domain reaching into the other's data.

### Future Expansion
RFID/barcode automation maturing into full real-time stock visibility · predictive replenishment maturing from the AI Opportunities above into standard operating behavior · unified asset-tag lookup once the Assets domain is documented and built, without merging the two domains' ownership.

### Commercial Differentiators
- **Lifecycle-based classification is the differentiator, not an implementation detail.** Most competing school ERPs model inventory as generic warehouse stock, borrowed wholesale from retail/general-ERP inventory management — which forces a school to work around the fact that a uniform, a laptop, and a ream of paper have completely different real accountability models. This platform's three-category lifecycle split matches how schools actually operate and are audited, which is a genuine, defensible product-positioning claim, not a schema choice nobody outside engineering would notice.
- **Cross-domain event propagation** — a Custody damage report can trigger Facilities' repair workflow and (once Assets exists) a depreciation adjustment automatically, because every domain in this platform shares one event bus. Siloed warehouse-management competitors can't do this; they don't have a Facilities or Assets domain to talk to in the first place.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0008](../adr/business/0008-inventory-classification-business-purpose.md) · [BUS-0009](../adr/business/0009-tracking-strategy-setting-not-classification.md) · [BUS-0010](../adr/business/0010-stock-movement-journal-entry-equivalent.md)
- **Related Domains:** [HR](hr.md), [Students](students.md), [Accounting](accounting.md), [Health Clinic](health-clinic.md), [School Operations](school-operations.md).
