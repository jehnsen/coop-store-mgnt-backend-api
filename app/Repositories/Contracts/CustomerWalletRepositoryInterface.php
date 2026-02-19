<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CustomerWallet;
use Illuminate\Database\Eloquent\Collection;

interface CustomerWalletRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Return all active wallets for the given customer, ordered by name.
     */
    public function getActiveByCustomer(int $customerId): Collection;

    /**
     * Find a wallet by UUID that belongs to a specific customer.
     * Returns null if the wallet does not exist or belongs to a different customer.
     */
    public function findByUuidAndCustomer(string $uuid, int $customerId): ?CustomerWallet;

    /**
     * Atomically decrement the wallet's raw balance column by $amountCentavos.
     *
     * Bypasses the Eloquent accessor (which would multiply by 100) so that the
     * caller can work with centavo integers throughout.
     *
     * @param  int $walletId       Primary key of the CustomerWallet record.
     * @param  int $amountCentavos Amount to deduct, in centavos.
     */
    public function deductBalance(int $walletId, int $amountCentavos): void;

    /**
     * Atomically increment the wallet's raw balance column by $amountCentavos.
     * Used when a wallet-charged sale is voided or refunded.
     *
     * @param  int $walletId       Primary key of the CustomerWallet record.
     * @param  int $amountCentavos Amount to restore, in centavos.
     */
    public function restoreBalance(int $walletId, int $amountCentavos): void;
}
