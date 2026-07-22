# Domain 2: Platform Services

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** none yet (predates the ADR system) · **Related Domains:** [Accounting](accounting.md) (invoice templates), [Academic](academic.md) (certificate templates), [HR](hr.md) (contract templates)

### Purpose
Shared, deployment-wide values every domain and every mobile app draws on, where no single domain owns the meaning.

### Responsibilities
Branding, Localization, the Document Template library and generation engine.

### Submodules
Branding · Localization · Document Templates · Document Engine (the underlying generation service)

### Master Data
**Document Template** — referenced by ID from every domain's generation requests (a Certificate template referenced by Academic, an Invoice template by Accounting, a Contract template by HR).

### Configuration
Default language · default timezone · default calendar system (Gregorian/Hijri) · organization-wide logo and color scheme · default currency display format (not the currency itself — that's Accounting's).

### Business Workflows
Template versioning (a new version, never an overwrite — the same Versioning Pattern already established for identity documents and Enrollment) · branding preview-then-publish (a single change affects every portal at once, so it's staged).

### Permissions
- **Branding Manager** — edit branding/localization/templates.
- **System Administrator** — full, including template management.

Deliberately its own permission group, not folded into `administration.*` — this isn't a security boundary.

### Reports
Template usage by consuming domain · localization coverage (% of strings translated).

### Mobile Applications
Indirect only — every app inherits branding/localization; there's no dedicated Platform Services screen in any app.

### Integrations
None today. A future e-signature Provider (contract/consent signing) is the most likely first addition to the Document Engine.

### Cross-Domain Dependencies
Every domain **consumes** Document Templates (Accounting for invoices, Academic for certificates, HR for contracts); none **own** the template engine itself.

### Future Growth
Per-Program branding overrides (School vs. Kindergarten distinct visual identity, consistent with Program-level settings eligibility already designed elsewhere) · e-signature · AI-assisted document drafting.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** [Accounting](accounting.md), [Academic](academic.md), [HR](hr.md) all consume Document Templates from here.
