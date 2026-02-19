<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC Phase 1: Links credit_transactions to a specific customer_wallet.
 *
 * When a sale is paid using a wallet (method: 'wallet'), the resulting
 * CreditTransaction record is tagged with the wallet that was charged.
 * This allows:
 *   - Per-wallet statement generation.
 *   - Per-wallet balance reconstruction from the ledger.
 *   - Audit trail showing which restricted fund was used for each purchase.
 *
 * NULL wallet_id means the transaction was created via the legacy
 * 'credit' payment method (single credit_limit on customer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreignId('wallet_id')
                  ->nullable()
                  ->after('sale_id')
                  ->constrained('customer_wallets')
                  ->nullOnDelete()
                  ->comment('FK to customer_wallets; null for legacy credit payments');

            $table->index('wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
            $table->dropIndex(['wallet_id']);
            $table->dropColumn('wallet_id');
        });
    }
};
