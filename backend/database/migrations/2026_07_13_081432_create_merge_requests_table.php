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
        Schema::create('merge_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('losing_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('winning_person_id')->constrained('people')->restrictOnDelete();

            // Nullable deliberately: a MergeRequest must not depend
            // exclusively on DuplicateFlag -- manual, API, and future
            // import-driven merges are supported from the same
            // migration/model, not bolted on later.
            $table->foreignId('duplicate_flag_id')->nullable()->constrained('duplicate_flags')->restrictOnDelete();

            $table->string('status')->default('draft');

            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();

            // Two separate FKs, not one -- the merge's own approval and
            // a later rollback's approval are two distinct cycles
            // (Sprint 3.2's final review: rollback requires the same
            // approval discipline as the merge itself). Explicit
            // columns avoid an ambiguous "latest ApprovalRequest for
            // this polymorphic requestable" query once both can exist.
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->restrictOnDelete();
            $table->foreignId('rollback_approval_request_id')->nullable()->constrained('approval_requests')->restrictOnDelete();

            // Populated at execute() time, immediately before the real
            // reassignment -- the losing Person's own field state as it
            // existed at merge time, per Addendum C8. Never touched by
            // dry-run or preview.
            $table->json('losing_person_snapshot')->nullable();

            // The most recent dry-run's outcome, whether it passed or
            // failed -- lets `draft` stay a single, re-checkable state
            // (Sprint 3.2's final review) instead of a separate
            // "dry_run_failed" status.
            $table->json('last_dry_run_result')->nullable();
            $table->timestamp('last_dry_run_at')->nullable();

            $table->timestamp('decided_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merge_requests');
    }
};
