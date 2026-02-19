<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CreditService::validateWalletUsage() when a cart item's product
 * category is not whitelisted in the wallet's allowed_category_ids.
 *
 * Example: paying for "San Miguel Beer" (category: Beverages, id: 4) using
 * the "Agri-Supply Wallet" (allowed_category_ids: [1, 2, 3]) will trigger
 * this exception.
 *
 * The exception is intentionally non-HTTP-specific so that it can be caught
 * and converted to the appropriate HTTP response in the controller or handler.
 */
class WalletRestrictionException extends RuntimeException
{
    public function __construct(
        private readonly string $walletName,
        private readonly string $productName,
        private readonly string $categoryName,
        private readonly int    $categoryId,
    ) {
        parent::__construct(
            "Wallet \"{$walletName}\" cannot be used to purchase \"{$productName}\" "
            . "(category: {$categoryName} [id:{$categoryId}]). "
            . 'Please use a wallet that covers this product category.'
        );
    }

    public function getWalletName(): string
    {
        return $this->walletName;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getCategoryName(): string
    {
        return $this->categoryName;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    /**
     * Structured array for JSON error responses.
     */
    public function toArray(): array
    {
        return [
            'wallet'    => $this->walletName,
            'product'   => $this->productName,
            'category'  => $this->categoryName,
            'category_id' => $this->categoryId,
        ];
    }
}
