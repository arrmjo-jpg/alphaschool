<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Credential Vault (docs/adr/0019-integration-platform-architecture.md
 * Decision 5) -- structurally parallel to configuration_values (same
 * Altitude + optimistic-locking + approval shape, ADR-0022 §1's "using
 * the Configuration Platform's own mechanisms directly"), but its own
 * table, model, and service (App\Modules\Administration\Services\
 * ProviderCredentialVault) -- never written through SettingsResolver or
 * stored in configuration_values, since ADR-0018 Decision 2 explicitly
 * rejected an `encrypted` flag on Configuration: a field needing
 * encryption is a Provider Credential, not Configuration.
 *
 * Always versioned, unlike Configuration's definition-level opt-in --
 * Decision 5: "a new credential write creates a new encrypted version;
 * prior versions are retained, never overwritten." There is no
 * non-versioned write path here at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('slot_key');
            $table->foreign('slot_key')->references('slot_key')->on('provider_registrations');

            $table->string('altitude');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();

            // Laravel's built-in encrypted cast (AES-256-CBC via APP_KEY)
            // -- the first genuinely encrypted-at-rest column in this
            // codebase (Playbook Phase 2: "the first genuinely encrypted
            // data here"). Never decrypted except inside
            // ProviderCredentialVault::resolve()'s return value.
            $table->text('credentials');

            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('active');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();

            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();

            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['slot_key', 'altitude', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_credentials');
    }
};
