<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Contracts
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CustomerWalletRepositoryInterface;  // MPC
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\PayableTransactionRepositoryInterface;
use App\Repositories\Contracts\DeliveryRepositoryInterface;

// Implementations
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\SaleRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\CustomerWalletRepository;             // MPC
use App\Repositories\Eloquent\PurchaseOrderRepository;
use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Eloquent\CreditTransactionRepository;
use App\Repositories\Eloquent\PayableTransactionRepository;
use App\Repositories\Eloquent\DeliveryRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository bindings
     *
     * @var array
     */
    protected array $repositories = [
        ProductRepositoryInterface::class             => ProductRepository::class,
        SaleRepositoryInterface::class                => SaleRepository::class,
        CustomerRepositoryInterface::class            => CustomerRepository::class,
        CustomerWalletRepositoryInterface::class      => CustomerWalletRepository::class,  // MPC
        SupplierRepositoryInterface::class            => SupplierRepository::class,
        PurchaseOrderRepositoryInterface::class       => PurchaseOrderRepository::class,
        CreditTransactionRepositoryInterface::class   => CreditTransactionRepository::class,
        PayableTransactionRepositoryInterface::class  => PayableTransactionRepository::class,
        DeliveryRepositoryInterface::class            => DeliveryRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
