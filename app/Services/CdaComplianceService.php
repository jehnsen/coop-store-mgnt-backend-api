<?php

namespace App\Services;

use App\Models\AgaRecord;
use App\Models\CdaAnnualReport;
use App\Models\CoopOfficer;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\MemberShareAccount;
use App\Models\MemberSavingsAccount;
use App\Models\MembershipApplication;
use App\Models\MembershipFee;
use App\Models\PatronageRefundBatch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\TimeDeposit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CdaComplianceService
{
    // =========================================================================
    // Annual Report
    // =========================================================================

    /**
     * Compile (or recompile) the annual report for a given year.
     *
     * Aggregates data from all MPC modules into a structured JSON snapshot.
     * The report is created as a draft if it doesn't exist yet; existing
     * draft reports are recompiled in place.
     *
     * @throws \RuntimeException if the report is already finalized/submitted.
     */
    public function compileAnnualReport(
        int $storeId,
        int $year,
        array $meta,
        User $operator
    ): CdaAnnualReport {
        $existing = CdaAnnualReport::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('report_year', $year)
            ->first();

        if ($existing && in_array($existing->status, ['finalized', 'submitted'])) {
            throw new \RuntimeException(
                "The {$year} annual report is already {$existing->status} and cannot be recompiled."
            );
        }

        $periodFrom = Carbon::create($year, 1, 1)->startOfDay();
        $periodTo   = Carbon::create($year, 12, 31)->endOfDay();

        $data = $this->aggregateReportData($storeId, $year, $periodFrom, $periodTo);

        return DB::transaction(function () use ($existing, $storeId, $year, $meta, $data, $periodFrom, $periodTo, $operator) {
            $attrs = [
                'store_id'          => $storeId,
                'report_year'       => $year,
                'period_from'       => $periodFrom->toDateString(),
                'period_to'         => $periodTo->toDateString(),
                'cda_reg_number'    => $meta['cda_reg_number'] ?? $existing?->cda_reg_number,
                'cooperative_type'  => $meta['cooperative_type'] ?? $existing?->cooperative_type,
                'area_of_operation' => $meta['area_of_operation'] ?? $existing?->area_of_operation,
                'report_data'       => $data,
                'status'            => 'draft',
                'compiled_by'       => $operator->id,
                'compiled_at'       => now(),
                'notes'             => $meta['notes'] ?? $existing?->notes,
            ];

            if ($existing) {
                $existing->update($attrs);
                $report = $existing->fresh();
            } else {
                $report = CdaAnnualReport::create($attrs);
            }

            activity()
                ->performedOn($report)
                ->causedBy($operator)
                ->withProperties(['report_year' => $year])
                ->log('cda_annual_report_compiled');

            return $report;
        });
    }

    /**
     * Update CDA metadata on a draft report without recompiling statistics.
     */
    public function updateReportMeta(CdaAnnualReport $report, array $data): CdaAnnualReport
    {
        if (in_array($report->status, ['finalized', 'submitted'])) {
            throw new \RuntimeException('Cannot update a finalized or submitted report. Create a new version instead.');
        }

        $report->update(array_filter([
            'cda_reg_number'    => $data['cda_reg_number'] ?? null,
            'cooperative_type'  => $data['cooperative_type'] ?? null,
            'area_of_operation' => $data['area_of_operation'] ?? null,
            'notes'             => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $report->fresh();
    }

    /**
     * Finalize a draft report — locks it from recompilation.
     */
    public function finalizeReport(CdaAnnualReport $report, User $operator): CdaAnnualReport
    {
        if ($report->status !== 'draft') {
            throw new \RuntimeException('Only draft reports can be finalized.');
        }

        return DB::transaction(function () use ($report, $operator) {
            $report->update([
                'status'       => 'finalized',
                'finalized_by' => $operator->id,
                'finalized_at' => now(),
            ]);

            activity()
                ->performedOn($report)
                ->causedBy($operator)
                ->log('cda_annual_report_finalized');

            return $report->fresh();
        });
    }

    /**
     * Record CDA submission details (reference number, submitted date).
     * A finalized report must exist before marking it submitted.
     */
    public function markSubmitted(CdaAnnualReport $report, array $data, User $operator): CdaAnnualReport
    {
        if ($report->status !== 'finalized') {
            throw new \RuntimeException('Only finalized reports can be marked as submitted.');
        }

        return DB::transaction(function () use ($report, $data, $operator) {
            $report->update([
                'status'               => 'submitted',
                'submitted_date'       => $data['submitted_date'] ?? today(),
                'submission_reference' => $data['submission_reference'] ?? null,
                'notes'                => $data['notes'] ?? $report->notes,
            ]);

            activity()
                ->performedOn($report)
                ->causedBy($operator)
                ->withProperties(['submission_reference' => $data['submission_reference'] ?? null])
                ->log('cda_annual_report_submitted');

            return $report->fresh();
        });
    }

    /**
     * Return a structured data payload formatted for the CDA Statistical Form 1
     * (General Information Sheet). This is the full report_data with officer list.
     */
    public function getStatisticalFormData(CdaAnnualReport $report): array
    {
        $store   = Store::find($report->store_id);
        $officers = CoopOfficer::where('store_id', $report->store_id)
            ->where('is_active', true)
            ->orderBy('committee')
            ->orderBy('position')
            ->get();

        return [
            'report_year'       => $report->report_year,
            'period_from'       => $report->period_from?->toDateString(),
            'period_to'         => $report->period_to?->toDateString(),
            'cda_reg_number'    => $report->cda_reg_number,
            'cooperative_type'  => $report->cooperative_type,
            'area_of_operation' => $report->area_of_operation,
            'cooperative'       => [
                'name'     => $store?->name,
                'address'  => $store?->address,
                'city'     => $store?->city,
                'province' => $store?->province,
                'phone'    => $store?->phone,
                'email'    => $store?->email,
                'tin'      => $store?->tin,
            ],
            'officers' => $officers->map(fn ($o) => [
                'name'      => $o->name,
                'position'  => $o->position,
                'committee' => $o->committee,
                'term_from' => $o->term_from?->toDateString(),
                'term_to'   => $o->term_to?->toDateString() ?? 'Present',
            ]),
            'statistics'        => $report->report_data ?? [],
        ];
    }

    // =========================================================================
    // AGA Records
    // =========================================================================

    /**
     * Create a new AGA/SGA record.
     */
    public function createAgaRecord(array $data, User $operator): AgaRecord
    {
        return DB::transaction(function () use ($data, $operator) {
            // Auto-compute quorum stats if attendance data provided
            $totalMembers   = (int) ($data['total_members'] ?? 0);
            $present        = (int) ($data['members_present'] ?? 0);
            $proxy          = (int) ($data['members_via_proxy'] ?? 0);
            $quorumPct      = $totalMembers > 0
                ? round(($present + $proxy) / $totalMembers * 100, 2)
                : 0;

            $record = AgaRecord::create([
                'store_id'          => $operator->store_id,
                'meeting_type'      => $data['meeting_type'] ?? 'annual',
                'meeting_year'      => $data['meeting_year'],
                'meeting_date'      => $data['meeting_date'],
                'venue'             => $data['venue'] ?? null,
                'total_members'     => $totalMembers,
                'members_present'   => $present,
                'members_via_proxy' => $proxy,
                'quorum_percentage' => $quorumPct,
                'quorum_achieved'   => $data['quorum_achieved'] ?? ($quorumPct >= 25),
                'presiding_officer' => $data['presiding_officer'] ?? null,
                'secretary'         => $data['secretary'] ?? null,
                'agenda'            => $data['agenda'] ?? null,
                'resolutions_passed'=> $data['resolutions_passed'] ?? null,
                'minutes_text'      => $data['minutes_text'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ]);

            activity()
                ->performedOn($record)
                ->causedBy($operator)
                ->withProperties(['aga_number' => $record->aga_number, 'year' => $record->meeting_year])
                ->log('aga_record_created');

            return $record;
        });
    }

    /**
     * Update a draft AGA record.
     */
    public function updateAgaRecord(AgaRecord $record, array $data, User $operator): AgaRecord
    {
        if ($record->status === 'finalized') {
            throw new \RuntimeException('Finalized AGA records cannot be edited.');
        }

        // Recompute quorum if attendance fields change
        $totalMembers = (int) ($data['total_members'] ?? $record->total_members);
        $present      = (int) ($data['members_present'] ?? $record->members_present);
        $proxy        = (int) ($data['members_via_proxy'] ?? $record->members_via_proxy);
        $quorumPct    = $totalMembers > 0
            ? round(($present + $proxy) / $totalMembers * 100, 2)
            : 0;

        $record->update(array_merge(
            array_filter($data, fn ($v, $k) => in_array($k, [
                'meeting_date', 'venue', 'presiding_officer', 'secretary',
                'agenda', 'resolutions_passed', 'minutes_text', 'notes',
            ]), ARRAY_FILTER_USE_BOTH),
            [
                'total_members'     => $totalMembers,
                'members_present'   => $present,
                'members_via_proxy' => $proxy,
                'quorum_percentage' => $quorumPct,
                'quorum_achieved'   => $data['quorum_achieved'] ?? ($quorumPct >= 25),
            ]
        ));

        return $record->fresh();
    }

    /**
     * Finalize AGA minutes — locks the record from further edits.
     */
    public function finalizeAgaRecord(AgaRecord $record, User $operator): AgaRecord
    {
        if ($record->status !== 'draft') {
            throw new \RuntimeException('Only draft AGA records can be finalized.');
        }

        return DB::transaction(function () use ($record, $operator) {
            $record->update([
                'status'       => 'finalized',
                'finalized_by' => $operator->id,
                'finalized_at' => now(),
            ]);

            activity()
                ->performedOn($record)
                ->causedBy($operator)
                ->log('aga_record_finalized');

            return $record->fresh();
        });
    }

    // =========================================================================
    // Officers
    // =========================================================================

    /**
     * Add an officer/director record.
     */
    public function createOfficer(array $data, User $operator): CoopOfficer
    {
        return CoopOfficer::create([
            'store_id'    => $operator->store_id,
            'customer_id' => $data['customer_id'] ?? null,
            'name'        => $data['name'],
            'position'    => $data['position'],
            'committee'   => $data['committee'] ?? null,
            'term_from'   => $data['term_from'],
            'term_to'     => $data['term_to'] ?? null,
            'is_active'   => true,
            'notes'       => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update an officer record.
     */
    public function updateOfficer(CoopOfficer $officer, array $data): CoopOfficer
    {
        $officer->update(array_filter([
            'name'        => $data['name'] ?? null,
            'position'    => $data['position'] ?? null,
            'committee'   => $data['committee'] ?? null,
            'term_from'   => $data['term_from'] ?? null,
            'term_to'     => $data['term_to'] ?? null,
            'is_active'   => $data['is_active'] ?? null,
            'notes'       => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $officer->fresh();
    }

    // =========================================================================
    // Overview
    // =========================================================================

    public function getOverview(int $storeId): array
    {
        $currentYear = now()->year;

        $latestReport = CdaAnnualReport::where('store_id', $storeId)
            ->orderBy('report_year', 'desc')
            ->first();

        return [
            'latest_report_year'      => $latestReport?->report_year,
            'latest_report_status'    => $latestReport?->status,
            'total_reports'           => CdaAnnualReport::where('store_id', $storeId)->count(),
            'submitted_reports'       => CdaAnnualReport::where('store_id', $storeId)->where('status', 'submitted')->count(),
            'total_aga_records'       => AgaRecord::where('store_id', $storeId)->count(),
            'aga_this_year'           => AgaRecord::where('store_id', $storeId)->where('meeting_year', $currentYear)->count(),
            'active_officers'         => CoopOfficer::where('store_id', $storeId)->where('is_active', true)->count(),
            'current_year_report_exists' => CdaAnnualReport::where('store_id', $storeId)->where('report_year', $currentYear)->exists(),
        ];
    }

    // =========================================================================
    // Private: Data Aggregation
    // =========================================================================

    private function aggregateReportData(int $storeId, int $year, Carbon $from, Carbon $to): array
    {
        // ── Membership ───────────────────────────────────────────────────────
        $totalMembers    = Customer::where('store_id', $storeId)->where('is_member', true)->count();
        $regularMembers  = Customer::where('store_id', $storeId)->where('member_status', 'regular')->count();
        $inactiveMembers = Customer::where('store_id', $storeId)->where('member_status', 'inactive')->count();
        $expelled        = Customer::where('store_id', $storeId)->where('member_status', 'expelled')->count();
        $resigned        = Customer::where('store_id', $storeId)->where('member_status', 'resigned')->count();
        $applicants      = Customer::where('store_id', $storeId)->where('member_status', 'applicant')->count();

        $admittedYtd = MembershipApplication::where('store_id', $storeId)
            ->where('status', 'approved')
            ->whereBetween('reviewed_at', [$from, $to])
            ->count();

        $rejectedYtd = MembershipApplication::where('store_id', $storeId)
            ->where('status', 'rejected')
            ->whereBetween('reviewed_at', [$from, $to])
            ->count();

        // ── Share Capital ────────────────────────────────────────────────────
        $shareCapitalSubscribed = (int) MemberShareAccount::where('store_id', $storeId)
            ->sum('total_subscribed_amount');
        $shareCapitalPaidUp = (int) MemberShareAccount::where('store_id', $storeId)
            ->whereIn('status', ['active'])
            ->sum('total_paid_up_amount');

        // ── Savings & Time Deposits ──────────────────────────────────────────
        $totalSavingsBalance = (int) MemberSavingsAccount::where('store_id', $storeId)
            ->where('status', 'active')
            ->sum('current_balance');
        $totalInterestCreditedYtd = (int) MemberSavingsAccount::where('store_id', $storeId)
            ->sum('total_interest_earned');
        $totalTimeDepositBalance = (int) TimeDeposit::where('store_id', $storeId)
            ->where('status', 'active')
            ->sum('current_balance');

        // ── Loans ────────────────────────────────────────────────────────────
        $totalLoansOutstanding = (int) Loan::where('store_id', $storeId)
            ->where('status', 'active')
            ->sum('remaining_balance');
        $totalLoansReleasedYtd = (int) Loan::where('store_id', $storeId)
            ->whereBetween('disbursement_date', [$from->toDateString(), $to->toDateString()])
            ->sum('approved_amount');

        // ── Patronage Refund ─────────────────────────────────────────────────
        $totalPatronageDistributedYtd = (int) PatronageRefundBatch::where('store_id', $storeId)
            ->whereYear('period_to', $year)
            ->sum('total_distributed');

        // ── Membership Fees ──────────────────────────────────────────────────
        $feesCollectedYtd = (int) MembershipFee::where('store_id', $storeId)
            ->where('is_reversed', false)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        // ── Sales Operations ─────────────────────────────────────────────────
        $totalSalesYtd = (int) Sale::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');
        $memberSalesYtd = (int) Sale::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereHas('customer', fn ($q) => $q->where('is_member', true))
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');

        // ── Active Officers ──────────────────────────────────────────────────
        $officers = CoopOfficer::where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('committee')
            ->orderBy('position')
            ->get()
            ->map(fn ($o) => [
                'name'      => $o->name,
                'position'  => $o->position,
                'committee' => $o->committee,
                'term_from' => $o->term_from?->toDateString(),
                'term_to'   => $o->term_to?->toDateString() ?? 'Present',
            ]);

        return [
            'compiled_at' => now()->toISOString(),
            'membership'  => [
                'total_members'    => $totalMembers,
                'regular_members'  => $regularMembers,
                'inactive_members' => $inactiveMembers,
                'expelled_members' => $expelled,
                'resigned_members' => $resigned,
                'pending_applicants' => $applicants,
                'admitted_ytd'     => $admittedYtd,
                'rejected_ytd'     => $rejectedYtd,
            ],
            'capital_structure' => [
                'share_capital_subscribed' => number_format($shareCapitalSubscribed / 100, 2, '.', ''),
                'share_capital_paid_up'    => number_format($shareCapitalPaidUp / 100, 2, '.', ''),
                'total_savings_balance'    => number_format($totalSavingsBalance / 100, 2, '.', ''),
                'total_time_deposit_balance' => number_format($totalTimeDepositBalance / 100, 2, '.', ''),
                'total_loans_outstanding'  => number_format($totalLoansOutstanding / 100, 2, '.', ''),
            ],
            'operations_ytd' => [
                'total_sales'                   => number_format($totalSalesYtd / 100, 2, '.', ''),
                'member_sales'                  => number_format($memberSalesYtd / 100, 2, '.', ''),
                'loans_released'                => number_format($totalLoansReleasedYtd / 100, 2, '.', ''),
                'savings_interest_credited'     => number_format($totalInterestCreditedYtd / 100, 2, '.', ''),
                'patronage_refund_distributed'  => number_format($totalPatronageDistributedYtd / 100, 2, '.', ''),
                'membership_fees_collected'     => number_format($feesCollectedYtd / 100, 2, '.', ''),
            ],
            'officers' => $officers,
        ];
    }
}
