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
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');

            // Eligibility is by role OR by a specific user (at least one
            // is required, validated by the engine at request() time --
            // not enforced at the schema level since "at least one of two
            // nullable columns" isn't expressible as a simple constraint
            // portable across MySQL/SQLite).
            $table->string('required_role')->nullable();

            // User IDs by convention, deliberately not constrained to the
            // `users` table -- see create_approval_requests_table for why
            // Core must not hold a schema-level FK into a Foundation
            // module's table.
            $table->unsignedBigInteger('required_user_id')->nullable();

            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->unsignedBigInteger('decided_by_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['approval_request_id', 'step_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
