<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC Phase 1: Creates the customer_wallets table.
 *
 * Each wallet represents a restricted credit facility for a cooperative member.
 * A single member may hold multiple wallets (e.g. "Grocery Credit", "Agri-Supply
 * Loan", "Emergency Fund"), each with its own:
 *
 *   - credit_limit         : Maximum credit the cooperative extends.
 *   - balance              : Currently available (unspent) credit in centavos.
 *   - allowed_category_ids : JSON whitelist of product-category IDs that may be
 *                            charged to this wallet.  The CreditService enforces
 *                            this restriction at sale time.
 *
 * Monetary columns (balance, credit_limit) follow the project-wide centavo
 * convention: bigInteger, value × 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('customer_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Descriptive name shown on receipts / member portal
            // e.g. "Grocery Credit", "Rice Production Loan", "Tractor Rental"
            $table->string('name', 100);

            // Available spendable balance in centavos
            $table->bigInteger('balance')->default(0)->comment('Available balance in centavos');

            // Cooperative-approved maximum credit ceiling in centavos
            $table->bigInteger('credit_limit')->default(0)->comment('Credit limit in centavos');

            // JSON array of Category.id values this wallet is allowed to pay for.
            // Example: [3, 7, 12]  (Fertilizer, Seeds, Pesticides)
            // An empty array [] means the wallet is blocked for all categories.
            $table->json('allowed_category_ids');

            // Frozen wallets cannot be charged; active wallets can.
            $table->enum('status', ['active', 'frozen'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Lookup indices
            $table->index('uuid');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};
