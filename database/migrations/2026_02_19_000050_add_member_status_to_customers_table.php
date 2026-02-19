<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Membership lifecycle status (null = non-member / walk-in customer)
            $table->enum('member_status', [
                'applicant',   // Application submitted, pending review
                'regular',     // Approved, active member
                'inactive',    // Member set to inactive (e.g. no transactions, arrears)
                'expelled',    // Removed by the cooperative
                'resigned',    // Voluntarily resigned
            ])->nullable()->after('is_member')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('member_status');
        });
    }
};
