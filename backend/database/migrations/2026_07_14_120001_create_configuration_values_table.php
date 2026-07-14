<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolved Configuration values, keyed by (configuration_key, altitude,
 * branch_id) -- the Global/Platform/Deployment/Organization/Branch chain
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 4). User Preferences are deliberately NOT an altitude row
 * here -- see configuration_user_preferences, a separate, lower-
 * ceremony table, per the same Decision.
 *
 * `version` is the optimistic-locking counter (ADR-0018 Decision 8) --
 * every write compares against a caller-supplied expected version;
 * SettingsResolver never overwrites blind.
 *
 * `status`/`approval_request_id` exist because an approval-required key
 * (ADR-0018 Decision 10's approval_permission) cannot write its value
 * live -- a pending row sits in 'pending_approval' until ApprovalEngine
 * (Core, already frozen) records a decision, exactly the same shape
 * already proven for MergeRequest's own approval_request_id column
 * (Sprint 3.2), reused here as a pattern, not shared code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_values', function (Blueprint $table) {
            $table->id();
            $table->string('configuration_key');
            $table->string('altitude');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();

            $table->json('value');
            $table->unsignedInteger('version')->default(1);

            $table->string('status')->default('active');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();

            // Populated only when the owning definition is versioned =
            // true (Blueprint §7's "never overwrite history" applied to
            // calculation-feeding Configuration) -- a superseded row's
            // effective_until is set rather than the row being updated
            // or deleted. Null/null for the ordinary, non-versioned case.
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();

            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['configuration_key', 'altitude', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_values');
    }
};
