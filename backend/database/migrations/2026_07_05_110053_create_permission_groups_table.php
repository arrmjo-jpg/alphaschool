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
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();

            // Immutable, code/seeder-defined -- referenced by the Admin
            // Workspace registry (docs/ADMIN_PLATFORM.md), never
            // parsed from a permission's own name.
            $table->string('code')->unique();

            // Spatie Translatable JSON -- system vocabulary, not a
            // personal/transliterated name (Addendum B5's distinction).
            $table->json('name');
            $table->json('description')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_groups');
    }
};
