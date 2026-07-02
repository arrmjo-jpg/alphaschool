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
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code'); // e.g. 'student_number', 'invoice_number'

            // Optional scoping -- e.g. one sequence per branch, or per
            // academic year (docs/DOMAIN_BLUEPRINT.md §6/Addendum A9).
            // Deliberately NOT NULLable: MySQL/MariaDB treat every NULL as
            // distinct in a unique index, so two "global" sequences for
            // the same code (both scope_type/scope_id = NULL) would NOT
            // violate a unique constraint and silently create a duplicate
            // sequence. '' / 0 are used as the explicit "no scope"
            // sentinel instead, so the unique index below actually works.
            $table->string('scope_type')->default('');
            $table->unsignedBigInteger('scope_id')->default(0);

            // '{number}' is replaced by the zero-padded value, e.g.
            // 'STU-{number}' with padding_length=5 -> 'STU-00042'. Null
            // pattern means the padded number alone.
            $table->string('format_pattern')->nullable();
            $table->unsignedTinyInteger('padding_length')->default(0);

            // null|'yearly'|'monthly' -- when set, current_value resets to
            // 0 whenever the computed period key changes.
            $table->string('reset_period')->nullable();
            $table->string('period_key')->nullable();

            $table->unsignedBigInteger('current_value')->default(0);

            // Documentation/reporting metadata only -- does NOT change
            // this service's locking behavior. A legally gapless sequence
            // (e.g. invoices) still gets that guarantee only if the
            // CALLER invokes next() from within its own outer transaction
            // wrapping the whole record-creation operation, so a later
            // failure rolls back the increment too via Laravel's
            // savepoint-based nested transactions. See
            // docs/developer/number-generator.md.
            $table->boolean('is_gapless')->default(false);

            $table->timestamps();

            $table->unique(['code', 'scope_type', 'scope_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
