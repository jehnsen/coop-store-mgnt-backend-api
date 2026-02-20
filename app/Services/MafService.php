<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\MafBeneficiary;
use App\Models\MafClaim;
use App\Models\MafClaimPayment;
use App\Models\MafContribution;
use App\Models\MafProgram;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mutual Aid Fund service — all MAF business logic.
 *
 * Covers:
 *   - Benefit program management (CRUD)
 *   - Beneficiary registration
 *   - Member contribution ledger (record + reversal)
 *   - Claims lifecycle (file → review → approve/reject → pay)
 *   - Fund balance and reporting
 */
class MafService
{
    // =========================================================================
    // A. Benefit Program Management
    // =========================================================================

    /**
     * Create a new MAF benefit program for the authenticated user's store.
     */
    public function createProgram(array $data): MafProgram
    {
        $storeId = auth()->user()->store_id;

        $program = MafProgram::create([
            'store_id'            => $storeId,
            'code'                => strtoupper(trim($data['code'])),
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'benefit_type'        => $data['benefit_type'],
            'benefit_amount'      => $data['benefit_amount'],   // mutator converts pesos → centavos
            'waiting_period_days' => $data['waiting_period_days'] ?? 0,
            'max_claims_per_year' => $data['max_claims_per_year'] ?? null,
            'is_active'           => $data['is_active'] ?? true,
        ]);



        return $program->fresh();
    }

    /**
     * Update an existing MAF benefit program.
     */
    public function updateProgram(MafProgram $program, array $data): MafProgram
    {
        $fillable = array_filter([
            'code'                => isset($data['code'])                ? strtoupper(trim($data['code'])) : null,
            'name'                => $data['name']                ?? null,
            'description'         => array_key_exists('description', $data)         ? $data['description']         : $program->description,
            'benefit_type'        => $data['benefit_type']        ?? null,
            'benefit_amount'      => $data['benefit_amount']      ?? null,
            'waiting_period_days' => $data['waiting_period_days'] ?? null,
            'max_claims_per_year' => array_key_exists('max_claims_per_year', $data) ? $data['max_claims_per_year'] : $program->max_claims_per_year,
            'is_active'           => $data['is_active']           ?? null,
        ], fn ($v) => $v !== null);

        $program->update($fillable);



        return $program->fresh();
    }

    // =========================================================================
    // B. Beneficiary Management
    // =========================================================================

    /**
     * Register a new beneficiary for a member.
     *
     * If is_primary is true, all other beneficiaries for this customer are
     * automatically demoted so only one primary exists at a time.
     */
    public function registerBeneficiary(Customer $customer, array $data): MafBeneficiary
    {
        return DB::transaction(function () use ($customer, $data) {
            if (!empty($data['is_primary'])) {
                // Demote any existing primary beneficiaries
                MafBeneficiary::where('customer_id', $customer->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $beneficiary = MafBeneficiary::create([
                'store_id'       => $customer->store_id,
                'customer_id'    => $customer->id,
                'name'           => $data['name'],
                'relationship'   => $data['relationship'],
                'birth_date'     => $data['birth_date'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'is_primary'     => $data['is_primary'] ?? false,
                'is_active'      => true,
                'notes'          => $data['notes'] ?? null,
            ]);



            return $beneficiary;
        });
    }

    /**
     * Update a beneficiary's details.
     */
    public function updateBeneficiary(MafBeneficiary $beneficiary, array $data): MafBeneficiary
    {
        return DB::transaction(function () use ($beneficiary, $data) {
            if (!empty($data['is_primary'])) {
                MafBeneficiary::where('customer_id', $beneficiary->customer_id)
                    ->where('is_primary', true)
                    ->where('id', '!=', $beneficiary->id)
                    ->update(['is_primary' => false]);
            }

            $beneficiary->update(array_filter([
                'name'           => $data['name']           ?? null,
                'relationship'   => $data['relationship']   ?? null,
                'birth_date'     => $data['birth_date']     ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'is_primary'     => $data['is_primary']     ?? null,
                'notes'          => $data['notes']          ?? null,
            ], fn ($v) => $v !== null));

            return $beneficiary->fresh();
        });
    }

    /**
     * Deactivate a beneficiary so they can no longer be named on new claims.
     * Existing claims that reference this beneficiary are not affected.
     */
    public function deactivateBeneficiary(MafBeneficiary $beneficiary): MafBeneficiary
    {
        $beneficiary->update(['is_active' => false, 'is_primary' => false]);



        return $beneficiary->fresh();
    }

    // =========================================================================
    // C. Contribution Ledger
    // =========================================================================

    /**
     * Record a MAF fund contribution from a member.
     *
     * @throws ValidationException When the customer is not an active member.
     */
    public function recordContribution(Customer $customer, array $data): MafContribution
    {
        if (!$customer->is_member) {
            throw ValidationException::withMessages([
                'customer_id' => ['Only cooperative members can make MAF contributions.'],
            ]);
        }

        $contribution = MafContribution::create([
            'store_id'          => $customer->store_id,
            'customer_id'       => $customer->id,
            'user_id'           => auth()->id(),
            'amount'            => $data['amount'],   // mutator: pesos → centavos
            'payment_method'    => $data['payment_method'],
            'reference_number'  => $data['reference_number'] ?? null,
            'contribution_date' => $data['contribution_date'] ?? now()->toDateString(),
            'period_year'       => $data['period_year'],
            'period_month'      => $data['period_month'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'is_reversed'       => false,
        ]);



        return $contribution->load(['customer', 'user']);
    }

    /**
     * Reverse a contribution (e.g. bounced check, data entry error).
     * Guards against double-reversal.
     *
     * @throws ValidationException When the contribution is already reversed.
     */
    public function reverseContribution(MafContribution $contribution, string $reason): MafContribution
    {
        if ($contribution->is_reversed) {
            throw ValidationException::withMessages([
                'contribution' => ['This contribution has already been reversed.'],
            ]);
        }

        $contribution->update([
            'is_reversed'     => true,
            'reversed_at'     => now(),
            'reversed_by'     => auth()->id(),
            'reversal_reason' => $reason,
        ]);



        return $contribution->fresh(['customer', 'user', 'reversedByUser']);
    }

    // =========================================================================
    // D. Claims Workflow
    // =========================================================================

    /**
     * File a new MAF benefit claim for a member.
     *
     * Business rules enforced:
     *   - Member must have is_member = true.
     *   - Waiting period: membership approval_date + waiting_period_days ≤ incident_date.
     *   - max_claims_per_year: count of approved/paid claims for this program in the
     *     calendar year of the incident must be less than the program's limit.
     *
     * @throws ValidationException On any business rule violation.
     */
    public function fileClaim(Customer $customer, MafProgram $program, array $data): MafClaim
    {
        if (!$customer->is_member) {
            throw ValidationException::withMessages([
                'customer_id' => ['Only cooperative members may file MAF claims.'],
            ]);
        }

        $incidentDate = Carbon::parse($data['incident_date']);

        // --- Waiting period check -------------------------------------------
        if ($program->waiting_period_days > 0 && $customer->member_since) {
            $eligibleFrom = Carbon::parse($customer->member_since)
                ->addDays($program->waiting_period_days);

            if ($incidentDate->lt($eligibleFrom)) {
                throw ValidationException::withMessages([
                    'incident_date' => [sprintf(
                        'Member is not yet eligible for "%s". Eligibility begins on %s '
                        . '(%d-day waiting period from membership date %s).',
                        $program->name,
                        $eligibleFrom->toFormattedDateString(),
                        $program->waiting_period_days,
                        Carbon::parse($customer->member_since)->toFormattedDateString(),
                    )],
                ]);
            }
        }

        // --- Max claims per year check ---------------------------------------
        if ($program->max_claims_per_year !== null) {
            $claimsThisYear = MafClaim::where('customer_id', $customer->id)
                ->where('maf_program_id', $program->id)
                ->whereIn('status', ['approved', 'paid'])
                ->whereYear('incident_date', $incidentDate->year)
                ->count();

            if ($claimsThisYear >= $program->max_claims_per_year) {
                throw ValidationException::withMessages([
                    'maf_program_uuid' => [sprintf(
                        'Maximum of %d claim(s) per year has been reached for program "%s" in %d.',
                        $program->max_claims_per_year,
                        $program->name,
                        $incidentDate->year,
                    )],
                ]);
            }
        }

        // --- Resolve beneficiary --------------------------------------------
        $beneficiaryId = null;
        if (!empty($data['beneficiary_uuid'])) {
            $beneficiary = MafBeneficiary::where('uuid', $data['beneficiary_uuid'])
                ->where('customer_id', $customer->id)
                ->first();

            if (!$beneficiary || !$beneficiary->is_active) {
                throw ValidationException::withMessages([
                    'beneficiary_uuid' => ['Beneficiary not found or is inactive.'],
                ]);
            }

            $beneficiaryId = $beneficiary->id;
        }

        $claim = MafClaim::create([
            'store_id'             => $customer->store_id,
            'customer_id'          => $customer->id,
            'maf_program_id'       => $program->id,
            'beneficiary_id'       => $beneficiaryId,
            'benefit_type'         => $program->benefit_type,  // snapshot
            'incident_date'        => $incidentDate->toDateString(),
            'claim_date'           => $data['claim_date'] ?? now()->toDateString(),
            'incident_description' => $data['incident_description'],
            'supporting_documents' => $data['supporting_documents'] ?? null,
            'claimed_amount'       => $data['claimed_amount'],  // mutator: pesos → centavos
            'approved_amount'      => null,
            'status'               => 'pending',
            'notes'                => $data['notes'] ?? null,
        ]);



        return $claim->load(['customer', 'mafProgram', 'beneficiary']);
    }

    /**
     * Mark a pending claim as under_review.
     *
     * @throws ValidationException When claim is not in pending status.
     */
    public function reviewClaim(MafClaim $claim, User $reviewer): MafClaim
    {
        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages([
                'claim' => ["Only pending claims can be put under review. Current status: {$claim->status}."],
            ]);
        }

        $claim->update([
            'status'      => 'under_review',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);



        return $claim->fresh(['customer', 'mafProgram', 'beneficiary', 'reviewedBy']);
    }

    /**
     * Approve a claim and set the approved_amount.
     *
     * @param  int $approvedCentavos Approved payout in CENTAVOS.
     * @throws ValidationException When claim is not under_review, or approved amount
     *                             exceeds the program benefit ceiling.
     */
    public function approveClaim(MafClaim $claim, int $approvedCentavos, User $approver): MafClaim
    {
        if ($claim->status !== 'under_review') {
            throw ValidationException::withMessages([
                'claim' => ["Only claims under review can be approved. Current status: {$claim->status}."],
            ]);
        }

        $benefitCeiling = (int) $claim->mafProgram->getRawOriginal('benefit_amount');

        if ($approvedCentavos > $benefitCeiling) {
            throw ValidationException::withMessages([
                'approved_amount' => [sprintf(
                    'Approved amount (₱%s) exceeds the program benefit ceiling of ₱%s.',
                    number_format($approvedCentavos / 100, 2),
                    number_format($benefitCeiling / 100, 2),
                )],
            ]);
        }

        $claim->update([
            'status'          => 'approved',
            'approved_amount' => $approvedCentavos,   // raw centavos, bypasses mutator
            'approved_by'     => $approver->id,
            'approved_at'     => now(),
        ]);



        return $claim->fresh(['customer', 'mafProgram', 'beneficiary', 'approvedBy']);
    }

    /**
     * Reject a claim with a mandatory reason.
     *
     * @throws ValidationException When claim is not under_review.
     */
    public function rejectClaim(MafClaim $claim, string $reason, User $rejector): MafClaim
    {
        if ($claim->status !== 'under_review') {
            throw ValidationException::withMessages([
                'claim' => ["Only claims under review can be rejected. Current status: {$claim->status}."],
            ]);
        }

        $claim->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by'      => $rejector->id,
            'rejected_at'      => now(),
        ]);



        return $claim->fresh(['customer', 'mafProgram', 'beneficiary', 'rejectedBy']);
    }

    /**
     * Disburse payment for an approved claim.
     *
     * Creates an immutable MafClaimPayment record and updates the claim
     * status to paid inside a single DB transaction.
     *
     * @param  array $paymentData Keys: payment_method, reference_number?, payment_date?, notes?
     * @throws ValidationException When claim is not in approved status.
     */
    public function payClaim(MafClaim $claim, array $paymentData): MafClaimPayment
    {
        if ($claim->status !== 'approved') {
            throw ValidationException::withMessages([
                'claim' => ["Only approved claims can be paid. Current status: {$claim->status}."],
            ]);
        }

        $approvedCentavos = (int) $claim->getRawOriginal('approved_amount');

        return DB::transaction(function () use ($claim, $paymentData, $approvedCentavos) {
            $payment = MafClaimPayment::create([
                'store_id'         => $claim->store_id,
                'claim_id'         => $claim->id,
                'customer_id'      => $claim->customer_id,
                'user_id'          => auth()->id(),
                'amount'           => $approvedCentavos,   // raw centavos bypasses mutator
                'payment_method'   => $paymentData['payment_method'],
                'reference_number' => $paymentData['reference_number'] ?? null,
                'payment_date'     => $paymentData['payment_date'] ?? now()->toDateString(),
                'notes'            => $paymentData['notes'] ?? null,
            ]);

            $claim->update([
                'status'  => 'paid',
                'paid_by' => auth()->id(),
                'paid_at' => now(),
            ]);



            return $payment->load(['claim', 'customer', 'user']);
        });
    }

    // =========================================================================
    // E. Reporting
    // =========================================================================

    /**
     * Overall MAF fund overview for the store.
     *
     * Returns:
     *   - total_contributions   Total active (non-reversed) contributions in pesos
     *   - total_disbursed       Total claim payments made in pesos
     *   - fund_balance          total_contributions − total_disbursed
     *   - claims_by_status      Count and amount per status
     *   - contributions_by_year Breakdown of contributions per year
     *   - program_summary       Per-program claim counts and amounts
     */
    public function getFundOverview(Store $store): array
    {
        $storeId = $store->id;

        // --- Contribution totals --------------------------------------------
        $totalContributionsCentavos = MafContribution::where('store_id', $storeId)
            ->where('is_reversed', false)
            ->sum('amount');

        // --- Disbursement totals --------------------------------------------
        $totalDisbursedCentavos = MafClaimPayment::where('store_id', $storeId)
            ->sum('amount');

        // --- Claims by status -----------------------------------------------
        $claimsByStatus = MafClaim::where('store_id', $storeId)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(approved_amount), SUM(claimed_amount)) as total_centavos')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn ($row) => [
                'count'  => (int) $row->count,
                'amount' => round($row->total_centavos / 100, 2),
            ]);

        // --- Contributions by year ------------------------------------------
        $contributionsByYear = MafContribution::where('store_id', $storeId)
            ->where('is_reversed', false)
            ->selectRaw('period_year, COUNT(*) as count, SUM(amount) as total_centavos')
            ->groupBy('period_year')
            ->orderBy('period_year', 'desc')
            ->get()
            ->map(fn ($row) => [
                'year'   => $row->period_year,
                'count'  => (int) $row->count,
                'amount' => round($row->total_centavos / 100, 2),
            ]);

        // --- Per-program summary --------------------------------------------
        $programSummary = MafProgram::where('store_id', $storeId)
            ->withCount('claims')
            ->get()
            ->map(fn ($program) => [
                'uuid'           => $program->uuid,
                'code'           => $program->code,
                'name'           => $program->name,
                'benefit_type'   => $program->benefit_type,
                'benefit_amount' => $program->benefit_amount,
                'is_active'      => $program->is_active,
                'claims_count'   => $program->claims_count,
            ]);

        return [
            'total_contributions' => round($totalContributionsCentavos / 100, 2),
            'total_disbursed'     => round($totalDisbursedCentavos / 100, 2),
            'fund_balance'        => round(($totalContributionsCentavos - $totalDisbursedCentavos) / 100, 2),
            'claims_by_status'    => $claimsByStatus,
            'contributions_by_year' => $contributionsByYear,
            'program_summary'     => $programSummary,
        ];
    }

    /**
     * Get all active (non-reversed) contributions for a member.
     */
    public function getMemberContributions(Customer $customer): Collection
    {
        return MafContribution::where('customer_id', $customer->id)
            ->with(['user'])
            ->orderBy('contribution_date', 'desc')
            ->get();
    }

    /**
     * Get all claims filed by a member.
     */
    public function getMemberClaims(Customer $customer): Collection
    {
        return MafClaim::where('customer_id', $customer->id)
            ->with(['mafProgram', 'beneficiary', 'payment'])
            ->orderBy('claim_date', 'desc')
            ->get();
    }

    /**
     * Claims report for a date range, grouped by benefit_type and status.
     *
     * @param  Carbon $from  Start of date range (inclusive), based on claim_date.
     * @param  Carbon $to    End of date range (inclusive).
     */
    public function getClaimsReport(Store $store, Carbon $from, Carbon $to): array
    {
        $claims = MafClaim::where('store_id', $store->id)
            ->whereBetween('claim_date', [$from->toDateString(), $to->toDateString()])
            ->with(['customer', 'mafProgram', 'beneficiary', 'payment'])
            ->orderBy('claim_date', 'desc')
            ->get();

        $byBenefitType = $claims->groupBy('benefit_type')->map(fn ($group) => [
            'count'           => $group->count(),
            'claimed_total'   => round($group->sum(fn ($c) => $c->getRawOriginal('claimed_amount')) / 100, 2),
            'approved_total'  => round($group->sum(fn ($c) => (int) $c->getRawOriginal('approved_amount')) / 100, 2),
            'paid_total'      => round($group->where('status', 'paid')
                ->sum(fn ($c) => (int) $c->getRawOriginal('approved_amount')) / 100, 2),
        ]);

        $byStatus = $claims->groupBy('status')->map(fn ($group) => [
            'count' => $group->count(),
        ]);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'totals' => [
                'claims_count'   => $claims->count(),
                'claimed_total'  => round($claims->sum(fn ($c) => $c->getRawOriginal('claimed_amount')) / 100, 2),
                'approved_total' => round($claims->sum(fn ($c) => (int) $c->getRawOriginal('approved_amount')) / 100, 2),
                'paid_total'     => round($claims->where('status', 'paid')
                    ->sum(fn ($c) => (int) $c->getRawOriginal('approved_amount')) / 100, 2),
            ],
            'by_benefit_type' => $byBenefitType,
            'by_status'       => $byStatus,
            'claims'          => $claims->map(fn ($claim) => [
                'claim_number'        => $claim->claim_number,
                'claim_date'          => $claim->claim_date->toDateString(),
                'incident_date'       => $claim->incident_date->toDateString(),
                'customer_name'       => $claim->customer->name,
                'member_id'           => $claim->customer->member_id,
                'program'             => $claim->mafProgram?->name,
                'benefit_type'        => $claim->benefit_type,
                'beneficiary'         => $claim->beneficiary?->name,
                'claimed_amount'      => $claim->claimed_amount,
                'approved_amount'     => $claim->approved_amount,
                'status'              => $claim->status,
                'payment_date'        => $claim->payment?->payment_date?->toDateString(),
            ]),
        ];
    }
}
