<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Delivery;
use App\Models\Customer;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    /**
     * Create a new receipt service instance.
     *
     * @param CreditTransactionRepositoryInterface $creditTransactionRepository
     */
    public function __construct(
        protected CreditTransactionRepositoryInterface $creditTransactionRepository
    ) {
    }
    /**
     * Generate receipt data for a sale.
     *
     * @param Sale $sale
     * @return array
     */
    public function generateReceiptData(Sale $sale): array
    {
        // Load all necessary relationships
        $sale->load([
            'store',
            'branch',
            'customer',
            'user',
            'items.product.unit',
            'payments'
        ]);

        $store = $sale->store;

        // Calculate totals
        $itemsTotal = $sale->items->where('quantity', '>', 0)->sum('line_total');
        $refundsTotal = abs($sale->items->where('quantity', '<', 0)->sum('line_total'));
        $paymentsTotal = $sale->payments->where('amount', '>', 0)->sum('amount');
        $refundPaymentsTotal = abs($sale->payments->where('amount', '<', 0)->sum('amount'));

        // VAT calculation (if VAT inclusive)
        $vatSales = 0;
        $vatAmount = $sale->vat_amount ?? 0;
        $vatExemptSales = 0;

        if ($store->is_vat_registered && $store->vat_inclusive) {
            $vatSales = $sale->total_amount - $vatAmount;
        } else {
            $vatExemptSales = $sale->total_amount;
        }

        return [
            // Store information
            'store' => [
                'name' => $store->name,
                'address' => $store->address,
                'city' => $store->city ?? '',
                'province' => $store->province ?? '',
                'phone' => $store->phone,
                'email' => $store->email,
                'tin' => $store->tin,
                'bir_permit_no' => $store->bir_permit_no,
                'logo_path' => $store->logo_path ? Storage::url($store->logo_path) : null,
                'is_vat_registered' => $store->is_vat_registered,
                'vat_rate' => $store->vat_rate ?? 12,
            ],

            // Branch information
            'branch' => [
                'name' => $sale->branch?->name ?? 'Main Branch',
                'address' => $sale->branch?->address ?? $store->address,
            ],

            // Sale information
            'sale' => [
                'number' => $sale->sale_number,
                'date' => $sale->sale_date->format('F d, Y'),
                'time' => $sale->sale_date->format('h:i A'),
                'status' => ucfirst($sale->status),
                'notes' => $sale->notes,
                'void_reason' => $sale->void_reason,
                'voided_at' => $sale->voided_at?->format('F d, Y h:i A'),
            ],

            // Customer information
            'customer' => $sale->customer ? [
                'name' => $sale->customer->name,
                'code' => $sale->customer->customer_code,
                'phone' => $sale->customer->phone,
                'address' => $sale->customer->address,
                'tin' => $sale->customer->tin,
                'type' => ucfirst(str_replace('_', ' ', $sale->customer->type)),
            ] : null,

            // Cashier information
            'cashier' => [
                'name' => $sale->user->name,
                'id' => $sale->user->employee_id ?? $sale->user->id,
            ],

            // Items
            'items' => $sale->items->where('quantity', '>', 0)->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit' => $item->product->unit->abbreviation ?? 'pcs',
                    'unit_price' => $item->unit_price,
                    'unit_price_display' => '₱' . number_format($item->unit_price / 100, 2),
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'discount_amount' => $item->discount_amount,
                    'discount_amount_display' => $item->discount_amount > 0
                        ? '-₱' . number_format($item->discount_amount / 100, 2)
                        : null,
                    'line_total' => $item->line_total,
                    'line_total_display' => '₱' . number_format($item->line_total / 100, 2),
                ];
            })->values(),

            // Refunded items (if any)
            'refunded_items' => $sale->items->where('quantity', '<', 0)->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => abs($item->quantity),
                    'unit' => $item->product->unit->abbreviation ?? 'pcs',
                    'unit_price_display' => '₱' . number_format($item->unit_price / 100, 2),
                    'line_total' => abs($item->line_total),
                    'line_total_display' => '₱' . number_format(abs($item->line_total) / 100, 2),
                ];
            })->values(),

            // Pricing breakdown
            'pricing' => [
                'subtotal' => $sale->subtotal,
                'subtotal_display' => '₱' . number_format($sale->subtotal / 100, 2),

                'discount_type' => $sale->discount_type,
                'discount_value' => $sale->discount_value,
                'discount_amount' => $sale->discount_amount,
                'discount_amount_display' => $sale->discount_amount > 0
                    ? '-₱' . number_format($sale->discount_amount / 100, 2)
                    : null,

                'vat_sales' => $vatSales,
                'vat_sales_display' => '₱' . number_format($vatSales / 100, 2),
                'vat_amount' => $vatAmount,
                'vat_amount_display' => '₱' . number_format($vatAmount / 100, 2),
                'vat_exempt_sales' => $vatExemptSales,
                'vat_exempt_sales_display' => '₱' . number_format($vatExemptSales / 100, 2),

                'total_amount' => $sale->total_amount,
                'total_amount_display' => '₱' . number_format($sale->total_amount / 100, 2),
            ],

            // Payments
            'payments' => $sale->payments->where('amount', '>', 0)->map(function ($payment) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $payment->method)),
                    'amount' => $payment->amount,
                    'amount_display' => '₱' . number_format($payment->amount / 100, 2),
                    'reference_number' => $payment->reference_number,
                ];
            })->values(),

            // Refund payments (if any)
            'refund_payments' => $sale->payments->where('amount', '<', 0)->map(function ($payment) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $payment->method)),
                    'amount' => abs($payment->amount),
                    'amount_display' => '₱' . number_format(abs($payment->amount) / 100, 2),
                    'reference_number' => $payment->reference_number,
                ];
            })->values(),

            // Totals
            'totals' => [
                'amount_paid' => $paymentsTotal,
                'amount_paid_display' => '₱' . number_format($paymentsTotal / 100, 2),
                'change_amount' => $paymentsTotal - $sale->total_amount,
                'change_amount_display' => '₱' . number_format(($paymentsTotal - $sale->total_amount) / 100, 2),
                'refund_amount' => $refundPaymentsTotal,
                'refund_amount_display' => '₱' . number_format($refundPaymentsTotal / 100, 2),
            ],

            // Header and footer text
            'header_text' => $store->receipt_header ?? 'Thank you for your purchase!',
            'footer_text' => $store->receipt_footer ?? 'Please come again!',

            // Additional info
            'is_voided' => $sale->status === 'voided',
            'is_refunded' => $sale->status === 'refunded',
            'has_refunds' => $refundsTotal > 0,

            // Generated timestamp
            'generated_at' => now()->format('F d, Y h:i A'),
        ];
    }

    /**
     * Generate PDF receipt for a sale.
     *
     * @param Sale $sale
     * @param string $size
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePDF(Sale $sale, string $size = 'a4'): \Barryvdh\DomPDF\PDF
    {
        $data = $this->generateReceiptData($sale);

        // Select the appropriate view based on size
        $view = $size === 'thermal' ? 'receipts.thermal' : 'receipts.standard';

        // Generate PDF
        $pdf = Pdf::loadView($view, $data);

        // Configure PDF settings based on size
        if ($size === 'thermal') {
            // Thermal printer size (80mm width)
            $pdf->setPaper([0, 0, 226.77, 500], 'portrait'); // 80mm width, variable height
        } else {
            // Standard A4 size
            $pdf->setPaper('a4', 'portrait');
        }

        return $pdf;
    }

    /**
     * Generate receipt data for a delivery.
     *
     * @param Delivery $delivery
     * @return array
     */
    public function generateDeliveryReceiptData(Delivery $delivery): array
    {
        $delivery->load([
            'store',
            'customer',
            'sale',
            'driver',
            'items.product.unit',
        ]);

        $store = $delivery->store;

        return [
            // Store information
            'store' => [
                'name' => $store->name,
                'address' => $store->address,
                'city' => $store->city ?? '',
                'province' => $store->province ?? '',
                'phone' => $store->phone,
                'email' => $store->email,
                'logo_path' => $store->logo_path ? Storage::url($store->logo_path) : null,
            ],

            // Delivery information
            'delivery' => [
                'number' => $delivery->delivery_number,
                'date' => $delivery->scheduled_date?->format('F d, Y'),
                'status' => ucfirst(str_replace('_', ' ', $delivery->status)),
                'notes' => $delivery->notes,
                'delivered_at' => $delivery->delivered_at?->format('F d, Y h:i A'),
            ],

            // Customer information
            'customer' => [
                'name' => $delivery->customer->name,
                'phone' => $delivery->customer->phone,
                'delivery_address' => $delivery->delivery_address,
            ],

            // Driver information
            'driver' => $delivery->driver ? [
                'name' => $delivery->driver->name,
                'phone' => $delivery->driver->phone,
            ] : null,

            // Related sale (if any)
            'sale' => $delivery->sale ? [
                'number' => $delivery->sale->sale_number,
                'date' => $delivery->sale->sale_date->format('F d, Y'),
            ] : null,

            // Items
            'items' => $delivery->items->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit' => $item->product->unit->abbreviation ?? 'pcs',
                    'notes' => $item->notes,
                ];
            }),

            // Proof of delivery
            'has_proof' => $delivery->proof_image_path !== null,
            'proof_image_path' => $delivery->proof_image_path
                ? Storage::url($delivery->proof_image_path)
                : null,

            // Generated timestamp
            'generated_at' => now()->format('F d, Y h:i A'),
        ];
    }

    /**
     * Generate PDF delivery receipt.
     *
     * @param Delivery $delivery
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateDeliveryPDF(Delivery $delivery): \Barryvdh\DomPDF\PDF
    {
        $data = $this->generateDeliveryReceiptData($delivery);

        $pdf = Pdf::loadView('receipts.delivery', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Generate customer statement data.
     *
     * @param Customer $customer
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function generateCustomerStatementData(Customer $customer, string $startDate, string $endDate): array
    {
        $customer->load('store');

        // Get transactions within date range using repository
        $transactions = $this->creditTransactionRepository->getStatementTransactions(
            $customer->id,
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );

        // Calculate opening balance using repository
        $openingBalance = $this->creditTransactionRepository->getOpeningBalance(
            $customer->id,
            \Carbon\Carbon::parse($startDate)
        );

        $runningBalance = $openingBalance;
        $totalCharges = 0;
        $totalPayments = 0;

        $transactionData = $transactions->map(function ($transaction) use (&$runningBalance, &$totalCharges, &$totalPayments) {
            $runningBalance += $transaction->amount;

            if ($transaction->amount > 0) {
                $totalCharges += $transaction->amount;
            } else {
                $totalPayments += abs($transaction->amount);
            }

            return [
                'date' => $transaction->transaction_date->format('M d, Y'),
                'type' => ucfirst($transaction->type),
                'description' => $transaction->description,
                'reference' => $transaction->reference_number ?? ($transaction->sale?->sale_number ?? ''),
                'charges' => $transaction->amount > 0
                    ? '₱' . number_format($transaction->amount / 100, 2)
                    : '',
                'payments' => $transaction->amount < 0
                    ? '₱' . number_format(abs($transaction->amount) / 100, 2)
                    : '',
                'balance' => '₱' . number_format($runningBalance / 100, 2),
                'days_outstanding' => $transaction->created_at->diffInDays(now()),
            ];
        });

        return [
            'store' => [
                'name' => $customer->store->name,
                'address' => $customer->store->address,
                'phone' => $customer->store->phone,
                'email' => $customer->store->email,
                'logo_path' => $customer->store->logo_path
                    ? Storage::url($customer->store->logo_path)
                    : null,
            ],

            'customer' => [
                'name' => $customer->name,
                'code' => $customer->customer_code,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'credit_limit' => '₱' . number_format($customer->credit_limit / 100, 2),
                'credit_terms_days' => $customer->credit_terms_days,
            ],

            'statement' => [
                'start_date' => \Carbon\Carbon::parse($startDate)->format('F d, Y'),
                'end_date' => \Carbon\Carbon::parse($endDate)->format('F d, Y'),
                'opening_balance' => '₱' . number_format($openingBalance / 100, 2),
                'total_charges' => '₱' . number_format($totalCharges / 100, 2),
                'total_payments' => '₱' . number_format($totalPayments / 100, 2),
                'closing_balance' => '₱' . number_format($runningBalance / 100, 2),
            ],

            'transactions' => $transactionData,

            'aging' => $this->calculateAgingBuckets($customer),

            'generated_at' => now()->format('F d, Y h:i A'),
        ];
    }

    /**
     * Generate customer statement PDF.
     *
     * @param Customer $customer
     * @param string $startDate
     * @param string $endDate
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateCustomerStatementPDF(Customer $customer, string $startDate, string $endDate): \Barryvdh\DomPDF\PDF
    {
        $data = $this->generateCustomerStatementData($customer, $startDate, $endDate);

        $pdf = Pdf::loadView('receipts.customer-statement', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Calculate aging buckets for customer outstanding balance.
     *
     * @param Customer $customer
     * @return array
     */
    protected function calculateAgingBuckets(Customer $customer): array
    {
        $now = now();

        $buckets = [
            'current' => 0,      // 0-30 days
            '31_60' => 0,        // 31-60 days
            '61_90' => 0,        // 61-90 days
            'over_90' => 0,      // Over 90 days
        ];

        // Get unpaid charges using repository
        $unpaidCharges = $this->creditTransactionRepository->getUnpaidInvoices($customer->id);

        foreach ($unpaidCharges as $charge) {
            $daysOld = $charge->transaction_date->diffInDays($now);
            $unpaidAmount = $charge->amount;

            if ($daysOld <= 30) {
                $buckets['current'] += $unpaidAmount;
            } elseif ($daysOld <= 60) {
                $buckets['31_60'] += $unpaidAmount;
            } elseif ($daysOld <= 90) {
                $buckets['61_90'] += $unpaidAmount;
            } else {
                $buckets['over_90'] += $unpaidAmount;
            }
        }

        return [
            'current' => '₱' . number_format($buckets['current'] / 100, 2),
            '31_60' => '₱' . number_format($buckets['31_60'] / 100, 2),
            '61_90' => '₱' . number_format($buckets['61_90'] / 100, 2),
            'over_90' => '₱' . number_format($buckets['over_90'] / 100, 2),
            'total' => '₱' . number_format(array_sum($buckets) / 100, 2),
        ];
    }

    /**
     * Send receipt via email.
     *
     * @param Sale $sale
     * @param string $email
     * @return bool
     */
    public function sendReceiptEmail(Sale $sale, string $email): bool
    {
        // TODO: Implement email sending using Laravel Mail
        // This would use a Mailable class to send the PDF receipt
        return true;
    }

    /**
     * Send receipt via SMS.
     *
     * @param Sale $sale
     * @param string $phone
     * @return bool
     */
    public function sendReceiptSMS(Sale $sale, string $phone): bool
    {
        // TODO: Implement SMS sending
        // This would integrate with SMS provider (Semaphore, Twilio, etc.)
        return true;
    }
}
