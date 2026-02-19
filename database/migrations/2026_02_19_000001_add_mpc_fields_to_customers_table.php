<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC Phase 1: Augments the customers table with cooperative member fields.
 *
 * - is_member      : Drives VAT-exempt logic in SaleService::calculateTotals().
 * - member_id      : Human-readable cooperative membership number.
 * - accumulated_patronage : Tracks lifetime purchase volume for dividend
 *                    calculations.  Stored in CENTAVOS (bigInteger) to match
 *                    the project-wide monetary convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Co-op member flag – the primary switch for VAT exemption
            $table->boolean('is_member')->default(false)->after('allow_credit');

            // Human-readable membership number, globally unique across stores
            $table->string('member_id', 50)->nullable()->unique()->after('is_member');

            // Lifetime patronage volume in centavos (₱1 = 100 centavos)
            // Used for patronage-refund / dividend calculations
            $table->bigInteger('accumulated_patronage')->default(0)->after('member_id')
                  ->comment('Lifetime patronage in centavos');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_member', 'member_id', 'accumulated_patronage']);
        });
    }
};
