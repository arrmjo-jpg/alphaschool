# Number Generator

`App\Core\Services\NumberGeneratorService` centralizes number generation so no module writes its own `SELECT MAX(value)+1` (docs/DOMAIN_BLUEPRINT.md §6/§13).

## Why a naive implementation is dangerous

`SELECT MAX(value)+1` looks correct in single-developer testing and produces duplicate numbers under real concurrent load — two requests both read the same max value before either writes. This service instead locks the sequence row (`SELECT ... FOR UPDATE`) inside a transaction around the whole read-increment-write cycle, so a second concurrent caller genuinely blocks until the first commits.

This is proven, not just implemented: `tests/Feature/Core/NumberGeneratorConcurrencyTest.php` opens two independent real connections to the local MariaDB and shows the second connection's lock attempt actually times out while the first holds it — not a simulated race, a real one. (Sqlite, used for the rest of the test suite, doesn't have real row-level locking, so this specific test bypasses the testing environment's default connection deliberately — see the file's docblock.)

## Gapless vs. lenient sequences — a usage convention, not a service flag

`is_gapless` on `number_sequences` is documentation/reporting metadata only — it does not change the service's internal behavior. Gaplessness (required by tax law in many jurisdictions for invoice numbers) is achieved by **how the caller uses the service**: call `next()` from *within* the same outer transaction that creates the numbered record. If anything later in that transaction fails and rolls back, Laravel's savepoint-based nested transactions roll back the sequence increment too, so no gap is created. A lenient sequence (a student number) can call `next()` standalone and tolerate an occasional gap on failure.

## Scoping and periods

`code` + `scope_type` + `scope_id` together identify one sequence — e.g. `('student_number', 'branch', 3)` is independent of `('student_number', 'branch', 4)`. Unscoped sequences use empty-string/zero sentinels internally, not `NULL` — MySQL/MariaDB treat every `NULL` as distinct in a unique index, so two "global" sequences for the same code would not have violated a naive nullable unique constraint.

`reset_period` (`yearly`/`monthly`) resets `current_value` to 0 whenever the computed period key changes, compared against a stored `period_key` — no scheduled job required.

## Formatting

`format_pattern` uses a `{number}` placeholder, applied after `padding_length` zero-pads the raw value — e.g. `format_pattern = 'INV-{number}'`, `padding_length = 5` → `INV-00042`.
