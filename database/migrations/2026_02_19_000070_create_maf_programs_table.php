<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC MAF Module: Mutual Aid Fund benefit program definitions.
 *
 * Each program defines a specific benefit type (death, hospitalization, etc.)
 * with a fixed benefit_amount that the cooperative board approves for qualifying
 * member claims. Programs are store-scoped so each cooperative can configure
 * their own benefit schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maf_programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Human-readable code, unique per store (e.g. DEATH-01, HOSP-01)
            $table->string('code', 20);

            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->enum('benefit_type', [
                'death',
                'hospitalization',
                'disability',
                'calamity',
                'funeral',
            ]);

            // Maximum benefit payout per approved claim, in centavos
            $table->bigInteger('benefit_amount')->comment('Maximum benefit payout in centavos');

            // Member must have been active for at least this many days before the incident
            // to be eligible for this benefit. 0 = no waiting period.
            $table->unsignedInteger('waiting_period_days')->default(0);

            // Null = unlimited claims per year for this program
            $table->unsignedTinyInteger('max_claims_per_year')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'is_active']);
            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maf_programs');
    }
};
