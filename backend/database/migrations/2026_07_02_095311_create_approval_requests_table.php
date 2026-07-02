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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();

            // Polymorphic on purpose -- this is the one place in Core
            // where polymorphism is appropriate, because Approval's job
            // is intentionally shallow (track who must approve what and
            // record the decision) and doesn't need domain-specific
            // richness the way the Assignment pattern does
            // (docs/DOMAIN_BLUEPRINT.md §6/§13).
            $table->string('requestable_type');
            $table->unsignedBigInteger('requestable_id');

            $table->string('status')->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('requested_by_id')->constrained('users');
            $table->text('reason')->nullable();

            // Default true: no-self-approval is the safer default for
            // most real approval workflows (e.g. Identity Maintenance's
            // Merge/Anonymization never allow self-approval, even for
            // Super Admin). Callers with a genuine reason to allow it
            // (e.g. a trivial single-role school where the requester
            // legitimately also holds the approving role) opt out
            // explicitly, rather than the engine assuming permissiveness.
            $table->boolean('disallow_requester_as_approver')->default(true);

            $table->unsignedInteger('current_step_number')->default(1);
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['requestable_type', 'requestable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
