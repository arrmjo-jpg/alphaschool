<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Spatie Translatable JSON -- system vocabulary, not a
            // personal name (Addendum B5). `name` (Spatie's own column)
            // stays the plain immutable English code used by
            // hasRole()/can() checks.
            $table->json('display_name')->nullable()->after('name');
            $table->json('description')->nullable()->after('display_name');

            // Deactivation, not SoftDeletes -- same reasoning as Branch:
            // a "deleted" Role must not leave model_has_roles rows
            // pointing at a semantically-gone parent. Stops a role from
            // being newly assignable while historical grants stay
            // meaningful.
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_active']);
        });
    }
};
