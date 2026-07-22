# Domain 7: Accounting

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** [BUS-0010](../adr/business/0010-stock-movement-journal-entry-equivalent.md) (Inventory Valuation integration point) · **Related Domains:** [Academic](academic.md) (Academic Year for fee terms), [Students](students.md) (Enrollment), [Inventory](inventory.md) (valued Movement journal entries), [Platform Services](platform-services.md) (invoice templates)

### Purpose
Governs the organization's financial transactions — what's owed, what's paid, what's taxed, what's reported.

### Responsibilities
Fiscal Year, Fee structure and invoicing, payment processing, tax rules, general ledger, financial reporting.

### Submodules
Fiscal Year · Fee Management · Invoicing · Payments · Tax · Ledger/Journal · Financial Reports

### Master Data
**Fiscal Year, Chart of Accounts, Fee Category, Tax Rate** — all referenced externally: Invoices reference Fee Category; Journal entries reference Chart of Accounts.

### Configuration
Invoice numbering format · receipt numbering format · tax-rate assignment rules · late-payment penalty policy · payment reminder schedule.

### Business Workflows
Invoice generation (tied to Academic Year/term) · payment collection → receipt issuance · refund processing · fee waiver/scholarship application (consumes a Scholarships-domain decision, later phase) · month/year-end close.

### Permissions
- **Financial Manager** — full.
- **Accountant** — transaction entry, no policy changes.
- **Cashier** — payment collection only, read-only on invoices.
- **Auditor** — read-only, full access.

### Reports
Revenue by Fee Category/Branch · outstanding balance/aging · payment method breakdown · tax liability · Profit & Loss · Balance Sheet.

### Mobile Applications
**Student & Parent App**: invoice view, payment (via Payment Provider), payment history. No Employee App presence beyond Payroll's own domain.

### Integrations
**Payment Provider** — Stripe, HyperPay, PayPal, local banks, all interchangeable behind one slot, exactly the Provider Model this document is built around · banking file export for reconciliation.

### Cross-Domain Dependencies
Consumes Academic Year (Academic owns it), Enrollment (Students owns it), scholarship decisions (Scholarships owns it, later phase). Accounting owns the business rule for how money moves; nothing else does.

### Future Growth
Multi-currency support (already flagged elsewhere as deferred until a real customer need exists, not built speculatively) · automated bank reconciliation · budgeting/forecasting.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0010](../adr/business/0010-stock-movement-journal-entry-equivalent.md)
- **Related Domains:** [Academic](academic.md), [Students](students.md), [Inventory](inventory.md), [Platform Services](platform-services.md).
