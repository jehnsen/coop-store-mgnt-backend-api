<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC Phase 1: Adds is_service flag to the products table.
 *
 * Non-inventory service items (Tractor Rental, Solar Dryer Fees, Threshing
 * Service, etc.) should:
 *   - NOT trigger inventory deduction on sale.
 *   - NOT be subject to reorder-point / low-stock alerts.
 *   - Still be subject to wallet category restrictions.
 *
 * The InventoryService already guards deduction behind $product->track_inventory.
 * is_service is an additional semantic flag for the frontend and reporting layer.
 *
 * category_id is already indexed in the original migration (composite index
 * store_id + category_id).  No additional index is required here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_service')
                  ->default(false)
                  ->after('track_inventory')
                  ->comment('True for non-inventory services (e.g. tractor rental)');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};
