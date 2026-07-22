# Domain 4: Students

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** [BUS-0017](../adr/business/0017-academic-organizational-structure.md) (a student's Curriculum Path and Curriculum Specification version bind as part of Enrollment's own period record, effective-dated, not a new Assignment type) · **Related Domains:** [Academic](academic.md) (owns Year/Grade/Section/Curriculum Path/Curriculum Specification that Enrollment references), [Admissions](admissions.md) (hands off into this domain), [Accounting](accounting.md), [Learning](learning.md), [School Health Clinic](health-clinic.md), [Smart Campus](smart-campus.md), [Reception](reception.md) (a Visit may reference a Student) (all consume Student/Enrollment)

### Purpose
The permanent record of "this person is or was ever a student," and the mechanics of their enrollment period.

### Responsibilities
Student identity, Enrollment lifecycle (promote/repeat/transfer/withdraw/graduate), guardian relationship links (consumed from People/Family, not owned here), student documents.

### Submodules
Student Profile · Enrollment · Guardian Links · Student Documents

*(Discipline and Special Education, when their own phase arrives, reference Student — they are not submodules of it.)*

### Master Data
Student itself sits closer to a full aggregate than reference master data, but is genuinely referenced externally by every domain that touches a student — Academic, Accounting, Transportation, Library, LMS all key off Student/Enrollment by ID.

### Configuration
Student ID/numbering format (the domain's own Number Generator scheme) · required-document checklist per Grade/Program · default guardian-access permissions.

### Business Workflows
Enrollment creation (new student → first Enrollment) · Branch Transfer (close Enrollment, open at destination — already frozen) · Withdrawal · Graduation (terminal Enrollment status, not a new aggregate — already frozen) · Curriculum Path/Curriculum Specification binding and change (BUS-0017) — carried as part of Enrollment's own period record, the same mechanism already used for Section transfers; switching Track/Major mid-year is a real historical event, never a mutable field, and never a separate Assignment-type aggregate since Enrollment already is the period-scoped record this belongs on.

### Permissions
- **Registrar** — full Enrollment management.
- **Teacher** — read-only on own students.
- **Parent** — read-only on own child, via the Student & Parent App.

### Reports
Enrollment trend over time · withdrawal/attrition rate and stated reasons · demographic breakdown.

### Mobile Applications
The **Student & Parent App** is essentially this domain's primary surface: profile, enrollment status, documents.

### Integrations
Government student-ID registries, where a jurisdiction requires it — a Compliance-domain concern this domain feeds.

### Cross-Domain Dependencies
Owns the business rule for Enrollment outright. Academic, Accounting, Transportation, and Library all consume Student/Enrollment data; none own any part of it.

### Future Growth
Graduation handoff into the Alumni domain (later phase); cross-branch student mobility analytics.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** [Academic](academic.md), [Admissions](admissions.md), [Accounting](accounting.md), [Learning](learning.md), [Health Clinic](health-clinic.md), [Smart Campus](smart-campus.md).
