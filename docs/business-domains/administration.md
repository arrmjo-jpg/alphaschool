# Domain 1: Administration

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** none yet (predates the ADR system) · **Related Domains:** every domain (permission enforcement, Provider Registry credential storage)

### Purpose
Governs who can access the system and how the platform itself is secured and operated. Not a business domain — the security/operations perimeter every business domain sits behind.

### Responsibilities
Identity administration (Users, Roles, Permissions), security policy, credential storage for every external integration platform-wide (Provider Registry), audit trail access, backup and maintenance mode, system diagnostics.

### Submodules
Users & Roles · Permissions · Security Policy · Provider Registry · Audit Log Viewer · Backup & Maintenance · System Diagnostics

### Master Data
**Role** and **Permission** definitions — referenced by every user-role assignment across the platform. Everything else Administration touches (Organization, Branch) is owned by other domains and only consumed here for scoping.

### Configuration
Password policy (length, complexity, expiry) · Session timeout · MFA enforcement · Maintenance-mode message/schedule · Backup schedule and retention · Audit log retention period.

Explicitly **not** here: Organization/Branch details (master data, owned elsewhere), or any domain's choice of *which* registered provider to use (that's the owning domain's own configuration — Administration only stores the credential).

### Business Workflows
User onboarding (create → assign role → activate) and offboarding (deactivate → revoke sessions) · sensitive permission-grant approval · provider credential rotation.

### Permissions
- **System Administrator** — full.
- **Security Officer** — read-only + audit access, no user creation.
- **IT Support** — password reset only, no permission changes.

### Reports
Active users by role/branch · permission audit (who has what) · login failure report · provider health status · audit trail export.

### Mobile Applications
None. Back-office only — no presence in Student/Parent or Employee apps.

### Integrations
This *is* the integration layer for the rest of the platform — Provider Registry hosts credentials for every category named across every domain below (SMS, Email, WhatsApp, Push, Storage, Payment, Maps, Meeting, Authentication, LMS sync). Administration has no integrations of its own beyond hosting everyone else's.

### Cross-Domain Dependencies
Every domain consumes Administration's permission enforcement and, via Provider Registry, its credential storage. Administration itself depends on nothing else — already an enforced architectural rule, not a proposal.

### Future Growth
SSO/SAML expansion, delegated branch-level administration, login-anomaly detection (a genuine AI candidate once that platform service exists).

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** every domain depends on Administration for permissions and Provider Registry credentials.
