<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MembershipApplication;
use App\Models\MembershipFee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    /**
     * Submit a new membership application.
     *
     * Sets the customer's member_status to 'applicant'.
     * Does NOT set is_member=true — that only happens on approval.
     */
    public function submitApplication(array $data, User $operator): MembershipApplication
    {
        $customer = Customer::where('store_id', $operator->store_id)
            ->findOrFail($data['customer_id']);

        // Prevent duplicate pending applications
        $existing = MembershipApplication::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw new \RuntimeException('This customer already has a pending membership application.');
        }

        // For reinstatements, customer must have been a member before
        if (($data['application_type'] ?? 'new') === 'reinstatement' && ! $customer->is_member) {
            throw new \RuntimeException('Reinstatement applications are only valid for former members.');
        }

        return DB::transaction(function () use ($data, $customer, $operator) {
            $application = MembershipApplication::create([
                'store_id'              => $operator->store_id,
                'customer_id'           => $customer->id,
                'application_type'      => $data['application_type'] ?? 'new',
                'application_date'      => $data['application_date'] ?? today(),
                'civil_status'          => $data['civil_status'] ?? null,
                'occupation'            => $data['occupation'] ?? null,
                'employer'              => $data['employer'] ?? null,
                'monthly_income_range'  => $data['monthly_income_range'] ?? null,
                'beneficiary_info'      => $data['beneficiary_info'] ?? null,
                'admission_fee_amount'  => $data['admission_fee_amount'] ?? 0,
                'notes'                 => $data['notes'] ?? null,
            ]);

            // Mark customer as applicant
            $customer->update(['member_status' => 'applicant']);

            activity()
                ->performedOn($application)
                ->causedBy($operator)
                ->withProperties(['application_number' => $application->application_number])
                ->log('membership_application_submitted');

            return $application;
        });
    }

    /**
     * Approve a pending membership application.
     *
     * - Sets application status = approved
     * - Sets customer.is_member = true, member_status = 'regular'
     * - Auto-generates member_id if not already set (MBR-YYYY-NNNNNN)
     * - Optionally records the admission fee if payment details provided
     */
    public function approveApplication(
        MembershipApplication $application,
        array $data,
        User $operator
    ): MembershipApplication {
        if ($application->status !== 'pending') {
            throw new \RuntimeException('Only pending applications can be approved.');
        }

        return DB::transaction(function () use ($application, $data, $operator) {
            $now      = now();
            $customer = $application->customer;

            // Generate member_id if not already assigned
            if (empty($customer->member_id)) {
                $year    = $now->year;
                $count   = Customer::withoutGlobalScopes()
                    ->where('member_id', 'like', "MBR-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $memberId = sprintf('MBR-%d-%06d', $year, $count + 1);
            } else {
                $memberId = $customer->member_id;
            }

            // Update customer to full member
            $customer->update([
                'is_member'     => true,
                'member_status' => 'regular',
                'member_id'     => $memberId,
            ]);

            // Update application
            $application->update([
                'status'      => 'approved',
                'reviewed_by' => $operator->id,
                'reviewed_at' => $now,
                'notes'       => $data['notes'] ?? $application->notes,
            ]);

            // Record admission fee if payment details provided
            if (! empty($data['payment_method'])) {
                $feeAmount = $data['admission_fee_amount']
                    ?? $application->getRawOriginal('admission_fee_amount');

                if ($feeAmount > 0) {
                    MembershipFee::create([
                        'store_id'         => $operator->store_id,
                        'customer_id'      => $customer->id,
                        'user_id'          => $operator->id,
                        'application_id'   => $application->id,
                        'fee_type'         => 'admission_fee',
                        'amount'           => $feeAmount / 100, // accessor will convert pesos→centavos
                        'payment_method'   => $data['payment_method'],
                        'reference_number' => $data['reference_number'] ?? null,
                        'transaction_date' => $data['transaction_date'] ?? today(),
                        'notes'            => $data['fee_notes'] ?? null,
                    ]);
                }
            }

            activity()
                ->performedOn($application)
                ->causedBy($operator)
                ->withProperties(['member_id' => $memberId, 'customer_id' => $customer->id])
                ->log('membership_application_approved');

            return $application->fresh();
        });
    }

    /**
     * Reject a pending membership application.
     *
     * Reverts customer member_status to null (they remain a non-member customer).
     */
    public function rejectApplication(
        MembershipApplication $application,
        array $data,
        User $operator
    ): MembershipApplication {
        if ($application->status !== 'pending') {
            throw new \RuntimeException('Only pending applications can be rejected.');
        }

        return DB::transaction(function () use ($application, $data, $operator) {
            $application->update([
                'status'           => 'rejected',
                'reviewed_by'      => $operator->id,
                'reviewed_at'      => now(),
                'rejection_reason' => $data['rejection_reason'],
                'notes'            => $data['notes'] ?? null,
            ]);

            // Revert applicant status
            $application->customer->update(['member_status' => null]);

            activity()
                ->performedOn($application)
                ->causedBy($operator)
                ->withProperties(['reason' => $data['rejection_reason']])
                ->log('membership_application_rejected');

            return $application->fresh();
        });
    }

    /**
     * Record a standalone membership fee payment (annual dues, etc.)
     */
    public function recordFee(array $data, User $operator): MembershipFee
    {
        $customer = Customer::where('store_id', $operator->store_id)
            ->where('is_member', true)
            ->findOrFail($data['customer_id']);

        return DB::transaction(function () use ($data, $customer, $operator) {
            $fee = MembershipFee::create([
                'store_id'         => $operator->store_id,
                'customer_id'      => $customer->id,
                'user_id'          => $operator->id,
                'application_id'   => $data['application_id'] ?? null,
                'fee_type'         => $data['fee_type'],
                'amount'           => $data['amount'],
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? today(),
                'period_year'      => $data['period_year'] ?? null,
                'notes'            => $data['notes'] ?? null,
            ]);

            activity()
                ->performedOn($fee)
                ->causedBy($operator)
                ->withProperties(['fee_type' => $fee->fee_type, 'amount' => $fee->getRawOriginal('amount')])
                ->log('membership_fee_recorded');

            return $fee;
        });
    }

    /**
     * Reverse a membership fee (corrects data entry errors).
     */
    public function reverseFee(MembershipFee $fee, User $operator): MembershipFee
    {
        if ($fee->is_reversed) {
            throw new \RuntimeException('This fee has already been reversed.');
        }

        return DB::transaction(function () use ($fee, $operator) {
            $fee->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $operator->id,
            ]);

            activity()
                ->performedOn($fee)
                ->causedBy($operator)
                ->log('membership_fee_reversed');

            return $fee->fresh();
        });
    }

    /**
     * Set an active member to inactive.
     */
    public function deactivateMember(Customer $customer, array $data, User $operator): Customer
    {
        if ($customer->member_status !== 'regular') {
            throw new \RuntimeException('Only regular members can be set to inactive.');
        }

        return DB::transaction(function () use ($customer, $data, $operator) {
            $customer->update(['member_status' => 'inactive']);

            activity()
                ->performedOn($customer)
                ->causedBy($operator)
                ->withProperties(['reason' => $data['reason'] ?? null])
                ->log('member_deactivated');

            return $customer->fresh();
        });
    }

    /**
     * Reinstate an inactive member back to regular status.
     */
    public function reinstateMember(Customer $customer, array $data, User $operator): Customer
    {
        if ($customer->member_status !== 'inactive') {
            throw new \RuntimeException('Only inactive members can be reinstated.');
        }

        return DB::transaction(function () use ($customer, $data, $operator) {
            $customer->update(['member_status' => 'regular']);

            // Optionally record a reinstatement fee
            if (! empty($data['payment_method']) && ! empty($data['reinstatement_fee_amount'])) {
                MembershipFee::create([
                    'store_id'         => $operator->store_id,
                    'customer_id'      => $customer->id,
                    'user_id'          => $operator->id,
                    'fee_type'         => 'reinstatement_fee',
                    'amount'           => $data['reinstatement_fee_amount'],
                    'payment_method'   => $data['payment_method'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'transaction_date' => $data['transaction_date'] ?? today(),
                    'notes'            => $data['notes'] ?? null,
                ]);
            }

            activity()
                ->performedOn($customer)
                ->causedBy($operator)
                ->withProperties(['notes' => $data['notes'] ?? null])
                ->log('member_reinstated');

            return $customer->fresh();
        });
    }

    /**
     * Expel a member (disciplinary removal).
     * Can expel from regular or inactive status.
     */
    public function expelMember(Customer $customer, array $data, User $operator): Customer
    {
        if (! in_array($customer->member_status, ['regular', 'inactive'])) {
            throw new \RuntimeException('Only active or inactive members can be expelled.');
        }

        return DB::transaction(function () use ($customer, $data, $operator) {
            $customer->update([
                'is_member'     => false,
                'member_status' => 'expelled',
            ]);

            activity()
                ->performedOn($customer)
                ->causedBy($operator)
                ->withProperties(['reason' => $data['reason'] ?? null])
                ->log('member_expelled');

            return $customer->fresh();
        });
    }

    /**
     * Record a member's voluntary resignation.
     */
    public function resignMember(Customer $customer, array $data, User $operator): Customer
    {
        if (! in_array($customer->member_status, ['regular', 'inactive'])) {
            throw new \RuntimeException('Only active or inactive members can resign.');
        }

        return DB::transaction(function () use ($customer, $data, $operator) {
            $customer->update([
                'is_member'     => false,
                'member_status' => 'resigned',
            ]);

            activity()
                ->performedOn($customer)
                ->causedBy($operator)
                ->withProperties(['reason' => $data['reason'] ?? null])
                ->log('member_resigned');

            return $customer->fresh();
        });
    }

    /**
     * Membership overview stats for a store.
     */
    public function getOverview(int $storeId): array
    {
        $base = Customer::where('store_id', $storeId);

        return [
            'total_members'          => (clone $base)->where('is_member', true)->count(),
            'regular_members'        => (clone $base)->where('member_status', 'regular')->count(),
            'inactive_members'       => (clone $base)->where('member_status', 'inactive')->count(),
            'expelled_members'       => (clone $base)->where('member_status', 'expelled')->count(),
            'resigned_members'       => (clone $base)->where('member_status', 'resigned')->count(),
            'pending_applications'   => MembershipApplication::where('store_id', $storeId)
                                            ->where('status', 'pending')->count(),
            'approved_ytd'           => MembershipApplication::where('store_id', $storeId)
                                            ->where('status', 'approved')
                                            ->whereYear('reviewed_at', now()->year)->count(),
            'rejected_ytd'           => MembershipApplication::where('store_id', $storeId)
                                            ->where('status', 'rejected')
                                            ->whereYear('reviewed_at', now()->year)->count(),
            'total_fees_collected'   => number_format(
                MembershipFee::where('store_id', $storeId)
                    ->where('is_reversed', false)->sum('amount') / 100,
                2, '.', ''
            ),
        ];
    }
}
