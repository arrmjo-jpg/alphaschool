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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Cheap, nullable seam for a future Org -> School -> Branch
            // hierarchy -- deliberately not required this sprint.
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();

            // Zero cost now, room for a future regional-grouping layer
            // (e.g. a Regional Director overseeing multiple branches)
            // without a structural migration later. Not a current
            // requirement, just insurance.
            $table->foreignId('parent_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Immutable once set (enforced at the application layer, not
            // by a DB trigger) -- used by reports, integrations,
            // accounting, and inventory. Distinct from name_en/name_ar,
            // which a school may legitimately rebrand without changing
            // what every downstream system keys off of.
            $table->string('code')->unique();

            $table->string('name_en');
            $table->string('name_ar');

            // Deactivation, not SoftDeletes (docs/developer/... soft-
            // delete taxonomy): a "deleted" Branch must never leave
            // model_has_roles rows or future branch_id FKs pointing at
            // a semantically-gone parent. Branch is a reference/
            // structural entity, stopped from being newly assignable,
            // never trashed.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
