<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CustomerWallet;
use App\Repositories\Contracts\CustomerWalletRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CustomerWalletRepository extends BaseRepository implements CustomerWalletRepositoryInterface
{
    protected function model(): string
    {
        return CustomerWallet::class;
    }

    /** {@inheritdoc} */
    public function getActiveByCustomer(int $customerId): Collection
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /** {@inheritdoc} */
    public function findByUuidAndCustomer(string $uuid, int $customerId): ?CustomerWallet
    {
        /** @var CustomerWallet|null */
        return $this->newQuery()
            ->where('uuid', $uuid)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * {@inheritdoc}
     *
     * Uses a raw DB decrement to bypass the Eloquent mutator which would
     * multiply the value by 100 a second time.
     */
    public function deductBalance(int $walletId, int $amountCentavos): void
    {
        $this->newQuery()
            ->where('id', $walletId)
            ->decrement('balance', $amountCentavos);
    }

    /** {@inheritdoc} */
    public function restoreBalance(int $walletId, int $amountCentavos): void
    {
        $this->newQuery()
            ->where('id', $walletId)
            ->increment('balance', $amountCentavos);
    }
}
