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
        Schema::table('applicants', function (Blueprint $table) {
            // The thinnest possible fee/payment placeholder
            // (IMPLEMENTATION_PLAYBOOK.md Sprint 4.3's own explicit risk
            // note: "keep the stub deliberately, visibly thin") -- three
            // columns on the existing row, not a new model/table. Real
            // invoicing is Finance's own Phase 7 concern.
            $table->decimal('fee_amount', 8, 2)->nullable()->after('converted_at');
            $table->boolean('fee_paid')->default(false)->after('fee_amount');
            $table->dateTime('fee_paid_at')->nullable()->after('fee_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'fee_paid', 'fee_paid_at']);
        });
    }
};
