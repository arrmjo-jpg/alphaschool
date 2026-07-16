<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Provider Registry's schema-declaration table
 * (docs/adr/0019-integration-platform-architecture.md Decision 1) --
 * the Registry Pattern's second instance (ADR-0018 Decision 7), one row
 * per slot declared by a module's DeclaresProviderSlots manifest.
 * Structurally parallel to configuration_definitions, never the same
 * table -- ADR-0016 §4 names Provider Registry/Vault as its own
 * permitted shape. Written only by
 * App\Modules\Administration\Services\ProviderRegistry::sync().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_registrations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('slot_key')->unique();

            // A descriptive category string, or -- when the vendor
            // category genuinely needs behavioral polymorphism (Email,
            // OAuth, Push) -- the FQCN of the PHP interface every
            // concrete Provider for that category implements. Checked
            // reflectively at sync time (interface_exists() +
            // is_subclass_of()) when it names a real interface; this is
            // a runtime string check, never a static `use` import, so
            // it does not create a deptrac dependency from
            // Administration onto the owning module.
            $table->string('capability_contract');

            // The concrete class implementing DeclaresProviderSlots (and,
            // where declared, capability_contract) -- resolved through
            // the container by App\Modules\Administration\Services\
            // ProviderManager, never matched by a hardcoded vendor-name
            // switch (ADR-0019 Decision 1: "adding a new vendor... requires
            // zero changes to the Registry itself").
            $table->string('provider_class');

            // Which field names the Vault's encrypted payload for this
            // slot must contain -- e.g. ["host","port","username",
            // "password"] for SMTP, ["client_id","client_secret"] for
            // OAuth. Never the values themselves.
            $table->json('credential_fields');

            $table->string('owning_module');

            // Mirrors ADR-0018 Decision 9's mandatory-permission-field
            // guard -- Playbook Phase 2's own stated security line: "a
            // distinct, narrower permission gates them versus generic
            // Configuration access." Enforced at sync time by
            // ProviderRegistry, not by this column's nullability (see
            // configuration_definitions' identical precedent).
            $table->string('required_permission_to_edit')->nullable();

            $table->boolean('approval_required')->default(false);
            $table->string('approval_permission')->nullable();

            $table->string('deprecation_status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_registrations');
    }
};
