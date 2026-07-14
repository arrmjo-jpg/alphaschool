# Configuration Platform (Administration Platform Phase 1)

**Status:** Phase 1 complete. Governed by `docs/ADMINISTRATION_PLATFORM.md` and `docs/adr/0016` through `0022` (including the same-day amendment pack — ADR-0018 Decisions 8–10).

## Declaring a new setting

Implement `App\Core\Contracts\DeclaresSettingsSchema` and register the class in `config('administration.registered_settings_schemas')`. `App\Modules\Identity\Support\IdentityOtpSettings` is the canonical worked example — copy its shape, not its values.

```php
class MyModuleSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'my_module.some_setting',
                type: 'int',
                eligibleAltitudes: ['global'],           // or ['global', 'branch']
                owningModule: 'MyModule',
                capability: 'policy-configuration-governance',
                dataClassification: 'operational',        // identity | financial | academic | operational | audit
                requiredPermissionToView: 'my_module.view-settings',
                requiredPermissionToEdit: 'my_module.configure-settings',
                defaultValue: 10,
                required: true,
                validationRules: ['type' => 'int', 'min' => 1, 'max' => 100],
            ),
        ];
    }
}
```

Then run `php artisan administration:sync-settings`. Two fields are **mandatory with no default** (ADR-0018 Decision 9): `requiredPermissionToView`/`requiredPermissionToEdit`. Omitting either fails the sync outright — this is deliberate, not a bug to work around.

## The registration-time integrity heuristic (ADR-0018 Decision 10)

Sync will refuse (not silently warn) a key that trips one of these, unless `heuristicAcknowledgment` carries a real, specific justification:

- Validation rules beyond `type`/`regex`/`enum`/`min`/`max` — a signal the key is really a Business Rule (`docs/adr/0020`), not Configuration.
- Data Classification `identity`, `financial`, or `audit` combined with `approvalRequired: false`.
- (From Phase 5 onward, once the Dependency Graph exists) excessive `requires` fan-out.

`IdentityOtpSettings` genuinely trips the second condition and carries a real acknowledgment — read it as the template for how to justify a legitimate exception, not how to silence the check.

## Reading a value

```php
public function __construct(private readonly SettingsResolver $settings) {}

$resolved = $this->settings->resolve('my_module.some_setting', ConfigurationScopeContext::global());
// or ConfigurationScopeContext::forBranch($branchId)

$resolved->value;              // the resolved value, or the declared default
$resolved->resolvedAtAltitude; // 'branch' | 'global' | null (null means "default, no row exists yet")
$resolved->trace;              // AltitudeCheck[] -- every altitude checked, in order
$resolved->version;            // pass this back into write() as expectedVersion
```

Never cache the result yourself for longer than one request — the Resolver deliberately does not cache (Addendum A6's discipline: promote to caching only once profiling proves it necessary, not preemptively).

## Writing a value

```php
$this->settings->write($key, $newValue, $scope, $expectedVersion, $actor);
```

`$expectedVersion` is `resolve()`'s own `version` field — optimistic locking (ADR-0018 Decision 8). A mismatch throws `ConfigurationWriteConflictException`; catch it, re-`resolve()`, and retry with the fresh version. Never retry with the same stale version.

If the key's `approvalRequired` is true, `write()` does not activate the value — it creates a `pending_approval` row and routes it through the existing `ApprovalEngine` via `ApprovalRoutingResolver`. Call `SettingsResolver::activateApprovedWrite()` once `ApprovalEngine::approve()` has recorded a decision; never flip the status directly.

## Approval-gated keys need an `approvalPermission`

Distinct from `requiredPermissionToEdit` — the permission that may *propose* a change is not necessarily the permission that may *approve* it (the same four-eyes reasoning already applied to Person Merge, Addendum C10). A key with `approvalRequired: true` and no `approvalPermission` fails sync.

## What never belongs here

Business Rules (Promotion/Admission/Grading/Fee policies) — see `docs/adr/0020-effective-dated-business-policy-pattern.md`. Provider credentials — see `docs/adr/0019` (Phase 2). Anything needing multiple instances, sub-fields, or independent identity — that's Reference/Master Data, a real table owned by the relevant module, never a Configuration value.

## Testing

Use the shared `registerConfigurationSchemas([MyModuleSettings::class])` helper (`tests/Pest.php`) rather than re-deriving the `config()` + `ConfigurationRegistry::sync()` boilerplate per test file. See `tests/Feature/Administration/SettingsResolverTest.php` and `tests/Feature/Administration/ConfigurationRegistrySyncTest.php` for the negative-case patterns every guard in this platform is proven against.

## Provider SDK (scaffold only — real implementation is Phase 2)

`App\Core\Contracts\DeclaresProviderSlots` and `App\Core\ValueObjects\ProviderSlotDefinition` exist now so Phase 2 doesn't invent the registration shape under its own deadline. No Vault, no credential storage, no health-check runner exists yet — do not implement this contract for real before Phase 2 begins.
