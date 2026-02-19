<?php

namespace App\Providers;

use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Events\PurchaseOrderReceived;
use App\Listeners\LogSaleActivity;
use App\Listeners\UpdateCustomerTotals;
use App\Listeners\CreateAPInvoiceForReceivedPO;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SaleCompleted::class => [
            UpdateCustomerTotals::class,
        ],
        PurchaseOrderReceived::class => [
            CreateAPInvoiceForReceivedPO::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        LogSaleActivity::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
