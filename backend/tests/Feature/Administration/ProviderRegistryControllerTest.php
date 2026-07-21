<?php

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Contracts\HealthCheckable;
use App\Core\Contracts\TestsCredentials;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Models\ProviderCredential;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Phase F-B's adapter surface (docs/ADMIN_DESIGN_SYSTEM.md §27.13) --
 * verified as a real HTTP Feature test for the identical reason
 * ConfigurationControllerTest.php already documents: Spatie's team
 * context only persists within one PHP process, which a real curl
 * request to a running server never establishes.
 */
class ProviderRegistryControllerTestFixtureProvider implements DeclaresProviderSlots, HealthCheckable, TestsCredentials
{
    public const SLOT_KEY = 'test.controller-fixture';

    public function __construct(
        private readonly ProviderCredentialVault $vault,
    ) {}

    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: self::SLOT_KEY,
                capabilityContract: 'test.category',
                credentialFields: [
                    new ProviderCredentialFieldDefinition('api_key', 'text'),
                    new ProviderCredentialFieldDefinition('api_secret', 'secret'),
                ],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-controller-fixture',
            ),
        ];
    }

    public function healthCheck(): bool
    {
        $credentials = $this->vault->resolve(self::SLOT_KEY, ConfigurationScopeContext::global())->credentials;

        return $credentials !== null && filled($credentials['api_key'] ?? null);
    }

    public function testCredentials(array $credentials): bool
    {
        return filled($credentials['api_key'] ?? null) && ! str_contains($credentials['api_key'], 'bad');
    }
}

function userWithProviderFixturePermission(array $permissions, ?Branch $branch = null): User
{
    $branch ??= Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);

    if ($permissions !== []) {
        $group = PermissionGroup::firstOrCreate(['code' => 'provider-fixture-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);

        foreach ($permissions as $permission) {
            $permissionModel = Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['permission_group_id' => $group->id, 'display_name' => ['en' => $permission, 'ar' => $permission]],
            );
            $role->givePermissionTo($permissionModel);
        }

        $user->assignRole($role);
    }

    return $user->fresh();
}

beforeEach(function () {
    config(['administration.registered_provider_slots' => [ProviderRegistryControllerTestFixtureProvider::class]]);
    app(ProviderRegistry::class)->sync();
    // Every test in this file shares one slot key, so HealthCheckRunner's
    // own 60s cache (keyed by slot_key) would otherwise leak a stale
    // healthy/unhealthy result from a prior test into this one -- a
    // test-isolation artifact of Pest running every it() in the same PHP
    // process, not something a real request would ever hit.
    Cache::flush();
});

it('lists the fixture slot as needs-setup before any credential is configured', function () {
    $viewer = userWithProviderFixturePermission([]);

    $response = $this->actingAs($viewer)->getJson(route('administration.providers.index'));

    $response->assertOk()->assertJsonFragment(['key' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY, 'status' => 'needs-setup']);
});

it('lists the fixture slot as ready once a credential passing its health check is configured', function () {
    $editor = userWithProviderFixturePermission(['test.manage-controller-fixture']);

    app(ProviderCredentialVault::class)->write(
        ProviderRegistryControllerTestFixtureProvider::SLOT_KEY,
        ['api_key' => 'k1', 'api_secret' => 's1'],
        ConfigurationScopeContext::global(),
        0,
        $editor,
    );

    $viewer = userWithProviderFixturePermission([]);
    $response = $this->actingAs($viewer)->getJson(route('administration.providers.index'));

    $response->assertOk()->assertJsonFragment(['key' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY, 'status' => 'ready']);
});

it('exposes credential field types explicitly in the slot detail, never inferred from the name', function () {
    // No view permission required at all -- §27.2/§27.6: no per-slot
    // view permission exists in the backend to check.
    $viewer = userWithProviderFixturePermission([]);

    $response = $this->actingAs($viewer)->getJson(route('administration.providers.show', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]));

    $response->assertOk()->assertJson([
        'credentialFields' => [
            ['name' => 'api_key', 'type' => 'text'],
            ['name' => 'api_secret', 'type' => 'secret'],
        ],
    ]);
});

it('marks canEdit and canTest true only for a user holding the edit permission', function () {
    $editor = userWithProviderFixturePermission(['test.manage-controller-fixture']);

    $response = $this->actingAs($editor)->getJson(route('administration.providers.show', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]));

    $response->assertOk()->assertJson(['canEdit' => true, 'canTest' => true]);
});

it('never marks canEdit true for is_super_admin -- the write and test endpoints have no such bypass', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->getJson(route('administration.providers.show', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]));

    $response->assertOk()->assertJson(['canEdit' => false]);
});

/**
 * The single most important assertion in this file: Test Connection
 * (§27.5's Edit->Test->Save rule) must never write anything, at the
 * real backend, not just observed as a UI-state artifact the way the
 * Phase F-A fixture-only re-verification could show. Proven the same
 * way that re-verification was proven convincing -- an independent
 * re-read of the real persisted state (here, a genuine second HTTP call
 * to the detail endpoint) after Test, showing zero change, followed by
 * a real Save as the control case that *does* change it.
 */
it('never persists credentials through the test endpoint, proven by a fresh detail fetch showing zero change', function () {
    $editor = userWithProviderFixturePermission(['test.manage-controller-fixture']);

    $before = $this->actingAs($editor)->getJson(route('administration.providers.show', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]));
    expect($before->json('configured'))->toBeFalse()->and($before->json('version'))->toBe(0);

    $testResponse = $this->actingAs($editor)->postJson(
        route('administration.providers.test', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'good-key', 'api_secret' => 'good-secret']],
    );
    $testResponse->assertOk()->assertJson(['ok' => true]);

    expect(ProviderCredential::where('slot_key', ProviderRegistryControllerTestFixtureProvider::SLOT_KEY)->count())->toBe(0);

    $after = $this->actingAs($editor)->getJson(route('administration.providers.show', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]));
    expect($after->json('configured'))->toBeFalse()->and($after->json('version'))->toBe(0);

    // Control case: a real Save through the identical slot *does* change
    // the state a fresh fetch reports -- proving the assertions above
    // are meaningful, not just an inert endpoint that never changes
    // anything regardless of what's called.
    $this->actingAs($editor)->patchJson(
        route('administration.providers.write', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'good-key', 'api_secret' => 'good-secret'], 'expectedVersion' => 0],
    )->assertOk();

    $afterSave = $this->actingAs($editor)->getJson(route('administration.providers.show', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]));
    expect($afterSave->json('configured'))->toBeTrue()->and($afterSave->json('version'))->toBe(1);
});

it('rejects a test call from a user lacking the edit permission with 403', function () {
    $viewer = userWithProviderFixturePermission([]);

    $response = $this->actingAs($viewer)->postJson(
        route('administration.providers.test', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'k', 'api_secret' => 's']],
    );

    $response->assertStatus(403);
});

it('writes credentials when the user holds the edit permission, at the correct expectedVersion', function () {
    $editor = userWithProviderFixturePermission(['test.manage-controller-fixture']);

    $response = $this->actingAs($editor)->patchJson(
        route('administration.providers.write', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'k1', 'api_secret' => 's1'], 'expectedVersion' => 0],
    );

    $response->assertOk()->assertJson(['version' => 1, 'status' => 'active']);
});

it('rejects a write from a user lacking the edit permission with 403, even is_super_admin', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($superAdmin)->patchJson(
        route('administration.providers.write', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'k1', 'api_secret' => 's1'], 'expectedVersion' => 0],
    );

    $response->assertStatus(403);
});

it('rejects a write with a stale expectedVersion as a 409 carrying the real currentVersion', function () {
    $editor = userWithProviderFixturePermission(['test.manage-controller-fixture']);

    $this->actingAs($editor)->patchJson(
        route('administration.providers.write', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'k1', 'api_secret' => 's1'], 'expectedVersion' => 0],
    )->assertOk();

    $response = $this->actingAs($editor)->patchJson(
        route('administration.providers.write', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'k2', 'api_secret' => 's2'], 'expectedVersion' => 0],
    );

    $response->assertStatus(409)->assertJson(['currentVersion' => 1]);
});

it('rejects a write missing a declared credential field as a 422', function () {
    $editor = userWithProviderFixturePermission(['test.manage-controller-fixture']);

    $response = $this->actingAs($editor)->patchJson(
        route('administration.providers.write', ['slotKey' => ProviderRegistryControllerTestFixtureProvider::SLOT_KEY]),
        ['credentials' => ['api_key' => 'k1'], 'expectedVersion' => 0],
    );

    $response->assertStatus(422);
});
