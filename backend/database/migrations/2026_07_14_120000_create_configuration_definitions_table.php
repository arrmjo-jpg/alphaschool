<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Configuration Registry's schema-declaration table
 * (docs/adr/0018-configuration-platform-resolution-and-metadata.md
 * Decision 2, amended by Decisions 9-10). One row per key declared by a
 * module's DeclaresSettingsSchema manifest -- never runtime-mutable
 * through this table directly; only ConfigurationRegistry::sync() (the
 * deploy-time registration mechanism) writes here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('key')->unique();
            $table->string('type');

            // Blueprint B5's three-way translation test -- null when the
            // value is purely technical (never human-facing at all).
            $table->string('translatable_category')->nullable();

            $table->json('default_value')->nullable();
            $table->boolean('required')->default(false);

            // e.g. ["platform","branch"] -- which altitudes (ADR-0018
            // Decision 4) this key may be overridden at. Default-deny per
            // Blueprint B6: a key not listing "branch" here can never
            // acquire a branch-level row, checked by the Resolver's write
            // path, not merely by UI omission.
            $table->json('eligible_altitudes');

            // Mandatory true for any key feeding a calculation (Blueprint
            // §7) -- enforced at sync time by ConfigurationRegistry, not
            // just documented here.
            $table->boolean('versioned')->default(false);

            $table->string('owning_module');
            $table->string('capability');

            // ADR-0011's Data Classification enumeration (Identity,
            // Financial, Academic, Operational, Audit).
            $table->string('data_classification');

            $table->boolean('approval_required')->default(false);

            // ADR-0018 Decision 10: the permission a would-be approver
            // must hold, checked by ApprovalRoutingResolver. Required
            // (validated at sync time, not by this column's own
            // nullability -- see ConfigurationRegistry) whenever
            // approval_required is true; otherwise null.
            $table->string('approval_permission')->nullable();

            // ADR-0018 Decision 9: mandatory, no default, enforced by
            // ConfigurationRegistry at sync time -- registration fails
            // (not silently defaults to open access) if either is
            // omitted from the declaring manifest. Nullable at the
            // column level only because the sync-time check, not a DB
            // constraint, is the enforcement mechanism (it needs to
            // produce a clear, attributable error naming the offending
            // manifest, which a NOT NULL constraint violation cannot).
            $table->string('required_permission_to_view')->nullable();
            $table->string('required_permission_to_edit')->nullable();

            $table->boolean('restart_required')->default(false);
            $table->unsignedInteger('cache_ttl_seconds')->nullable();

            // [{key, value}] equality-only preconditions -- ADR-0018
            // Decision 2 deliberately excludes an expression language.
            $table->json('requires')->nullable();

            $table->json('validation_rules')->nullable();
            $table->string('migration_strategy')->nullable();
            $table->string('deprecation_status')->default('active');

            // ADR-0018 Decision 10: free-text justification a module
            // author must supply when a heuristic trigger fires
            // (dependency fan-out, validation-rule complexity, or a
            // Financial/Identity/Audit classification combined with
            // approval_required = false). A flagged key with no
            // acknowledgment fails sync outright -- this is what makes
            // "requires an explicit reviewer-acknowledgment comment to
            // merge" a machine-checkable fact rather than a hope.
            $table->text('heuristic_acknowledgment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_definitions');
    }
};
