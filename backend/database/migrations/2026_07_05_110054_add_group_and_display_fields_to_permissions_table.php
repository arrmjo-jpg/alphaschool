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
        Schema::table('permissions', function (Blueprint $table) {
            // Explicit FK, never parsed from the permission's own
            // `name` -- a permission's display grouping doesn't always
            // match its code prefix (docs/DOMAIN_BLUEPRINT.md, the
            // original design session's explicit correction).
            $table->foreignId('permission_group_id')->constrained('permission_groups')->restrictOnDelete();

            $table->json('display_name')->nullable()->after('name');
            $table->json('description')->nullable()->after('display_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('permission_group_id');
            $table->dropColumn(['display_name', 'description']);
        });
    }
};
