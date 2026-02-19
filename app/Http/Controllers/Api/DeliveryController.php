<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Http\Requests\Delivery\UpdateStatusRequest;
use App\Http\Requests\Delivery\UploadProofRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Sale;
use App\Models\User;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Criteria\FilterByColumn;
use App\Repositories\Criteria\FilterByDateRange;
use App\Repositories\Criteria\OrderBy;
use App\Repositories\Criteria\SearchMultipleColumns;
use App\Repositories\Criteria\WithRelations;
use App\Services\DeliveryService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryController extends Controller
{
    use ApiResponse;

    protected DeliveryService $deliveryService;
    protected DeliveryRepositoryInterface $deliveryRepository;

    public function __construct(
        DeliveryService $deliveryService,
        DeliveryRepositoryInterface $deliveryRepository
    ) {
        $this->deliveryService = $deliveryService;
        $this->deliveryRepository = $deliveryRepository;
    }

    /**
     * Display a paginated listing of deliveries.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        // Apply eager loading
        $this->deliveryRepository->pushCriteria(
            new WithRelations(['sale', 'customer', 'deliveryItems.product', 'assignedToUser'])
        );

        // Search by delivery number
        if ($request->filled('search')) {
            $this->deliveryRepository->pushCriteria(
                new SearchMultipleColumns($request->input('search'), ['delivery_number'])
            );
        }

        // Filter by status
        if ($request->filled('status')) {
            $this->deliveryRepository->pushCriteria(
                new FilterByColumn('status', $request->input('status'))
            );
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $customer = \App\Models\Customer::where('uuid', $request->input('customer_id'))->first();
            if ($customer) {
                $this->deliveryRepository->pushCriteria(
                    new FilterByColumn('customer_id', $customer->id)
                );
            }
        }

        // Filter by date range
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $this->deliveryRepository->pushCriteria(
                new FilterByDateRange(
                    'scheduled_date',
                    Carbon::parse($request->input('date_from')),
                    Carbon::parse($request->input('date_to'))
                )
            );
        } elseif ($request->filled('date_from')) {
            $this->deliveryRepository->pushCriteria(
                new FilterByColumn('scheduled_date', $request->input('date_from'), '>=')
            );
        } elseif ($request->filled('date_to')) {
            $this->deliveryRepository->pushCriteria(
                new FilterByColumn('scheduled_date', $request->input('date_to'), '<=')
            );
        }

        // Filter by driver
        if ($request->filled('driver_id')) {
            $this->deliveryRepository->pushCriteria(
                new FilterByColumn('assigned_to', $request->input('driver_id'))
            );
        }

        // Order by scheduled date descending
        $this->deliveryRepository->pushCriteria(
            new OrderBy('scheduled_date', 'desc')
        );

        $deliveries = $this->deliveryRepository->paginate($perPage);

        return $this->paginatedResponse(
            $deliveries->setCollection(
                $deliveries->getCollection()->map(fn($delivery) => new DeliveryResource($delivery))
            ),
            'Deliveries retrieved successfully'
        );
    }

    /**
     * Store a newly created delivery.
     */
    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        try {
            $sale = Sale::where('uuid', $request->sale_id)->firstOrFail();

            // Validate sale belongs to user's store
            if ($sale->store_id !== auth()->user()->store_id) {
                return $this->errorResponse('Sale does not belong to your store', null, 403);
            }

            $delivery = $this->deliveryService->createDelivery($sale, $request->validated());

            return $this->successResponse(
                new DeliveryResource($delivery),
                'Delivery created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified delivery.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $this->deliveryRepository->pushCriteria(
                new WithRelations([
                    'sale',
                    'customer',
                    'deliveryItems.product.unit',
                    'deliveryItems.saleItem',
                    'assignedToUser'
                ])
            );

            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            return $this->successResponse(
                new DeliveryResource($delivery),
                'Delivery retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Delivery not found', null, 404);
        }
    }

    /**
     * Update the specified delivery.
     */
    public function update(UpdateDeliveryRequest $request, string $uuid): JsonResponse
    {
        try {
            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            $delivery = $this->deliveryService->updateDelivery($delivery, $request->validated());

            return $this->successResponse(
                new DeliveryResource($delivery),
                'Delivery updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified delivery (soft delete).
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            // Only allow deletion if status is 'preparing'
            if ($delivery->status !== 'preparing') {
                return $this->errorResponse(
                    'Only deliveries with status "preparing" can be deleted',
                    null,
                    400
                );
            }

            $delivery->delete();

            return $this->successResponse(
                null,
                'Delivery deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Update delivery status.
     */
    public function updateStatus(UpdateStatusRequest $request, string $uuid): JsonResponse
    {
        try {
            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            $extraData = [];
            if ($request->filled('delivered_at')) {
                $extraData['delivered_at'] = $request->delivered_at;
            }
            if ($request->filled('failed_reason')) {
                $extraData['failed_reason'] = $request->failed_reason;
            }

            $delivery = $this->deliveryService->updateStatus(
                $delivery,
                $request->status,
                $request->notes,
                $extraData
            );

            return $this->successResponse(
                new DeliveryResource($delivery),
                "Delivery status updated to '{$request->status}' successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Upload proof of delivery.
     */
    public function uploadProof(UploadProofRequest $request, string $uuid): JsonResponse
    {
        try {
            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            $delivery = $this->deliveryService->uploadProofOfDelivery(
                $delivery,
                $request->file('proof_image'),
                $request->file('signature_image'),
                $request->received_by,
                $request->notes
            );

            return $this->successResponse(
                new DeliveryResource($delivery),
                'Proof of delivery uploaded successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Download proof of delivery image.
     */
    public function downloadProof(string $uuid)
    {
        try {
            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            if (!$delivery->proof_of_delivery_path) {
                return $this->errorResponse('No proof of delivery available', null, 404);
            }

            if (!Storage::disk('public')->exists($delivery->proof_of_delivery_path)) {
                return $this->errorResponse('Proof of delivery file not found', null, 404);
            }

            return Storage::disk('public')->download($delivery->proof_of_delivery_path);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Generate delivery receipt data (JSON).
     */
    public function receipt(string $uuid): JsonResponse
    {
        try {
            $this->deliveryRepository->pushCriteria(
                new WithRelations([
                    'sale',
                    'customer',
                    'deliveryItems.product.unit',
                    'deliveryItems.saleItem',
                    'assignedToUser',
                    'store',
                    'branch'
                ])
            );

            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            $receiptData = [
                'store' => [
                    'name' => $delivery->store->name ?? 'N/A',
                    'address' => $delivery->store->address ?? 'N/A',
                    'phone' => $delivery->store->phone ?? 'N/A',
                    'email' => $delivery->store->email ?? 'N/A',
                ],
                'delivery' => [
                    'number' => $delivery->delivery_number,
                    'status' => $delivery->status,
                    'scheduled_date' => $delivery->scheduled_date?->format('F d, Y'),
                    'scheduled_time' => $delivery->scheduled_date?->format('h:i A'),
                    'dispatched_at' => $delivery->dispatched_at?->format('F d, Y h:i A'),
                    'delivered_at' => $delivery->delivered_at?->format('F d, Y h:i A'),
                    'created_at' => $delivery->created_at?->format('F d, Y h:i A'),
                ],
                'customer' => [
                    'name' => $delivery->customer->name ?? 'Walk-in Customer',
                    'phone' => $delivery->customer->phone ?? $delivery->contact_phone,
                    'email' => $delivery->customer->email ?? 'N/A',
                ],
                'delivery_address' => [
                    'street' => $delivery->delivery_address,
                    'city' => $delivery->delivery_city,
                    'province' => $delivery->delivery_province,
                    'postal_code' => $delivery->delivery_postal_code,
                    'contact_person' => $delivery->contact_person,
                    'contact_phone' => $delivery->contact_phone,
                ],
                'sale' => [
                    'number' => $delivery->sale->sale_number,
                    'date' => $delivery->sale->sale_date?->format('F d, Y h:i A'),
                    'total_amount' => number_format($delivery->sale->total_amount, 2),
                ],
                'items' => $delivery->deliveryItems->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'unit' => $item->product->unit->abbreviation ?? 'pcs',
                        'status' => $item->status,
                    ];
                }),
                'instructions' => $delivery->delivery_notes,
                'driver' => $delivery->assignedToUser ? [
                    'name' => $delivery->assignedToUser->name,
                    'phone' => $delivery->assignedToUser->phone,
                ] : null,
                'received_by' => $delivery->received_by,
                'proof_url' => $delivery->proof_of_delivery_path
                    ? Storage::url($delivery->proof_of_delivery_path)
                    : null,
                'signature_url' => $delivery->signature_path
                    ? Storage::url($delivery->signature_path)
                    : null,
            ];

            return $this->successResponse($receiptData, 'Receipt data generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Generate delivery receipt PDF.
     */
    public function receiptPdf(string $uuid)
    {
        try {
            $this->deliveryRepository->pushCriteria(
                new WithRelations([
                    'sale',
                    'customer',
                    'deliveryItems.product.unit',
                    'deliveryItems.saleItem',
                    'assignedToUser',
                    'store',
                    'branch'
                ])
            );

            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            $data = [
                'delivery' => $delivery,
            ];

            $pdf = Pdf::loadView('deliveries.receipt', $data);

            return $pdf->download("delivery-receipt-{$delivery->delivery_number}.pdf");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Assign driver to delivery.
     */
    public function assignDriver(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $delivery = $this->deliveryRepository->findByUuidOrFail($uuid);

            $driver = User::where('id', $request->driver_id)
                ->where('store_id', auth()->user()->store_id)
                ->firstOrFail();

            $delivery = $this->deliveryService->assignDriver($delivery, $driver);

            return $this->successResponse(
                new DeliveryResource($delivery),
                'Driver assigned successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Get today's delivery schedule.
     */
    public function todaySchedule(): JsonResponse
    {
        try {
            $deliveries = $this->deliveryRepository->getTodaySchedule();

            return $this->successResponse(
                DeliveryResource::collection($deliveries),
                "Today's delivery schedule retrieved successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }
}
