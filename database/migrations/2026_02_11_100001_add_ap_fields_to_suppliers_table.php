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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->bigInteger('total_outstanding')->default(0)->after('payment_terms_days'); // in centavos
            $table->bigInteger('total_purchases')->default(0)->after('total_outstanding'); // in centavos
            $table->string('payment_rating')->default('good')->after('total_purchases'); // good, fair, poor
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['total_outstanding', 'total_purchases', 'payment_rating']);
        });
    }
};
