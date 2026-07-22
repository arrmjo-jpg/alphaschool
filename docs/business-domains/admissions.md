# Domain 5: Admissions

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** none yet (predates the ADR system) · **Related Domains:** [Students](students.md) (receives the handoff on acceptance)

### Purpose
The pre-enrollment funnel — turning a prospective family into an enrolled student.

### Responsibilities
Inquiry capture, Application management, Assessment/testing scheduling, Interview scheduling, Offer/acceptance, handoff to Students' Enrollment creation.

### Submodules
Inquiries · Applications · Assessments · Interviews · Offers · Waitlist

### Master Data
**Applicant** (a separate aggregate from Student, by prior design decision — an Applicant who is rejected or withdraws never becomes a Student record at all) · **Admission Cycle** (a specific year's admission round, referenced by every Application in it).

### Configuration
Required documents per Grade/Program · assessment passing threshold · application fee amount · waitlist policy · seat capacity per Grade/Section (capacity itself may be Academic's master data, consumed here).

### Business Workflows
The full funnel: Inquiry → Application → Assessment → Interview → Acceptance → Enrollment handoff. **This is the strongest single candidate across the whole platform for the deferred, configurable Workflow Engine** — the exact steps genuinely vary per school (some skip interviews entirely; some require committee review only for borderline assessment scores), which is precisely the "shape varies per customer" trigger condition that engine has always been waiting for.

### Permissions
- **Admissions Manager** — full.
- **Admissions Officer** — operate the funnel, no policy changes.
- **Interviewer** — read applicant info, submit interview scores only.

### Reports
Funnel conversion rate (inquiry → enrolled) · time-to-decision · source-of-inquiry breakdown · seat fill rate by Grade.

### Mobile Applications
A dedicated **Applicant-facing portal** — distinct from the Student & Parent App, since applicants aren't students yet — is a genuine current gap worth naming rather than assuming away: there is no prospective-family surface anywhere in this platform's scope today.

### Integrations
Payment Provider (application fees) · SMS/Email Provider (status updates to applicants) · online assessment delivery Providers.

### Cross-Domain Dependencies
Owns the entire pre-enrollment business rule. Hands off to Students the instant an offer is accepted — from Enrollment creation onward, Students owns everything.

### Future Growth
AI-assisted application screening, integrated online assessment delivery, multi-branch waitlist pooling.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** [Students](students.md).
