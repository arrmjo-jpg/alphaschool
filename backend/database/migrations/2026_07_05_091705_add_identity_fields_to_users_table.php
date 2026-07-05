<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A separate migration, run after `people` exists, since
     * `person_id`'s foreign key requires the referenced table to already
     * be present -- not a redesign of the base users table, just the
     * part of it that couldn't exist until Sprint 2.1 shipped Person.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // docs/DOMAIN_BLUEPRINT.md Addendum D4: every domain
            // aggregate gets a ULID public_id for external API/route
            // representation, never the raw internal id.
            $table->ulid('public_id')->unique()->after('id');

            // The ONLY link User has outward (docs/DOMAIN_BLUEPRINT.md
            // §8) -- one-way, single FK. Unique: one User account per
            // Person (not every Person needs a User row, but a Person
            // never holds two separate login accounts).
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();

            $table->string('username')->unique();
            $table->string('phone')->nullable()->unique();

            $table->string('status')->default('active');
            $table->timestamp('last_login_at')->nullable();

            // Entirely outside the Role system -- a Gate::before bypass
            // keyed off this flag, never a per-team role grant
            // (docs/DOMAIN_BLUEPRINT.md §8).
            $table->boolean('is_super_admin')->default(false);

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
            $table->dropColumn(['public_id', 'username', 'phone', 'status', 'last_login_at', 'is_super_admin', 'deleted_at']);
        });
    }
};
