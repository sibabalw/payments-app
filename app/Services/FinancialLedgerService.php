<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Models\Business;
use App\Models\FinancialLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Financial Ledger Service
 *
 * CRITICAL: The financial ledger is the SOURCE OF TRUTH for all balances.
 * All balance calculations must derive from ledger entries. Stored balances
 * in other tables (e.g., businesses.escrow_balance) are cached projections
 * for performance and should be rebuilt from ledger when discrepancies occur.
 */
class FinancialLedgerService
{
    /**
     * Account types for double-entry bookkeeping
     */
    public const ACCOUNT_ESCROW = 'ESCROW';

    public const ACCOUNT_PAYROLL = 'PAYROLL';

    public const ACCOUNT_PAYMENT = 'PAYMENT';

    public const ACCOUNT_FEES = 'FEES';

    public const ACCOUNT_TAXES = 'TAXES';

    public const ACCOUNT_BANK = 'BANK';

    /**
     * Transaction types
     */
    public const TYPE_DEBIT = 'DEBIT';

    public const TYPE_CREDIT = 'CREDIT';

    /**
     * Currency minor unit divisors (e.g., 100 for ZAR = cents)
     */
    protected array $currencyDivisors = [
        'ZAR' => 100, // Cents
        'USD' => 100, // Cents
        'EUR' => 100, // Cents
    ];

    protected SequenceService $sequenceService;

    public function __construct(
        ?SequenceService $sequenceService = null
    ) {
        $this->sequenceService = $sequenceService ?? app(SequenceService::class);
    }

    /**
     * Get BalancePrecalculationService instance (lazy-loaded to avoid circular dependency).
     */
    protected function getBalancePrecalculationService(): BalancePrecalculationService
    {
        return app(BalancePrecalculationService::class);
    }

    /**
     * Convert amount to minor units (cents for ZAR)
     */
    public function toMinorUnits(float $amount, string $currency = 'ZAR'): int
    {
        $divisor = $this->currencyDivisors[$currency] ?? 100;

        return (int) round($amount * $divisor);
    }

    /**
     * Convert minor units to decimal amount
     */
    public function fromMinorUnits(int $minorUnits, string $currency = 'ZAR'): float
    {
        $divisor = $this->currencyDivisors[$currency] ?? 100;

        return $minorUnits / $divisor;
    }

    /**
     * Record a double-entry transaction (debit and credit)
     *
     * CRITICAL: Both entries must use the same currency. Cross-currency transactions are forbidden.
     *
     * @param  string  $correlationId  Unique ID to group related entries
     * @param  string  $debitAccount  Account to debit (e.g., ESCROW)
     * @param  string  $creditAccount  Account to credit (e.g., PAYROLL)
     * @param  float  $amount  Transaction amount
     * @param  Business  $business  Business entity
     * @param  string|null  $description  Transaction description
     * @param  Model|null  $reference  Related model (PayrollJob, PaymentJob, etc.)
     * @param  array|null  $metadata  Additional metadata (before/after balances, etc.)
     * @param  \App\Models\User|null  $user  User who initiated the transaction
     * @param  string  $currency  Currency code (default: ZAR). Must be same for both entries.
     * @return array Array with 'debit' and 'credit' ledger entries
     */
    public function recordTransaction(
        string $correlationId,
        string $debitAccount,
        string $creditAccount,
        float $amount,
        Business $business,
        ?string $description = null,
        ?Model $reference = null,
        ?array $metadata = null,
        ?\App\Models\User $user = null,
        string $currency = 'ZAR'
    ): array {
        // Use write connection for ledger operations
        return DB::connection('mysql')->transaction(function () use (
            $correlationId,
            $debitAccount,
            $creditAccount,
            $amount,
            $business,
            $description,
            $reference,
            $metadata,
            $user,
            $currency
        ) {
            // Validate amount
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Amount must be greater than zero');
            }

            // Validate currency
            if (! isset($this->currencyDivisors[$currency])) {
                throw new \InvalidArgumentException("Unsupported currency: {$currency}");
            }

            // Validate account types
            $validAccounts = [
                self::ACCOUNT_ESCROW,
                self::ACCOUNT_PAYROLL,
                self::ACCOUNT_PAYMENT,
                self::ACCOUNT_FEES,
                self::ACCOUNT_TAXES,
                self::ACCOUNT_BANK,
            ];

            if (! in_array($debitAccount, $validAccounts, true)) {
                throw new \InvalidArgumentException("Invalid debit account type: {$debitAccount}");
            }

            if (! in_array($creditAccount, $validAccounts, true)) {
                throw new \InvalidArgumentException("Invalid credit account type: {$creditAccount}");
            }

            // Convert to minor units
            $amountMinorUnits = $this->toMinorUnits($amount, $currency);

            // Generate sequence numbers for deterministic ordering (global sequence)
            // Both entries get sequential numbers for total ordering
            // Use atomic sequence service instead of MAX() query for performance
            $debitSequence = $this->sequenceService->getNext();
            $creditSequence = $this->sequenceService->getNext(); // Credit immediately follows debit in sequence

            // Create debit entry (starts as PENDING, transitions to POSTED after settlement)
            $debit = FinancialLedger::create([
                'correlation_id' => $correlationId,
                'sequence_number' => $debitSequence,
                'transaction_type' => self::TYPE_DEBIT,
                'account_type' => $debitAccount,
                'business_id' => $business->id,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'amount' => $amount,
                'amount_minor_units' => $amountMinorUnits,
                'currency' => $currency,
                'description' => $description ?? "Debit from {$debitAccount}",
                'metadata' => $metadata,
                'user_id' => $user?->id,
                'effective_at' => now(),
                'posting_state' => FinancialLedger::POSTING_PENDING,
            ]);

            // Create credit entry (same currency, same amount, same posting state)
            $credit = FinancialLedger::create([
                'correlation_id' => $correlationId,
                'sequence_number' => $creditSequence,
                'transaction_type' => self::TYPE_CREDIT,
                'account_type' => $creditAccount,
                'business_id' => $business->id,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
                'amount' => $amount,
                'amount_minor_units' => $amountMinorUnits,
                'currency' => $currency, // Same currency - cross-currency transactions forbidden
                'description' => $description ?? "Credit to {$creditAccount}",
                'metadata' => $metadata,
                'user_id' => $user?->id,
                'effective_at' => now(),
                'posting_state' => FinancialLedger::POSTING_PENDING,
            ]);

            Log::info('Double-entry transaction recorded', [
                'correlation_id' => $correlationId,
                'debit_account' => $debitAccount,
                'credit_account' => $creditAccount,
                'amount' => $amount,
                'business_id' => $business->id,
                'debit_id' => $debit->id,
                'credit_id' => $credit->id,
            ]);

            return [
                'debit' => $debit,
                'credit' => $credit,
            ];
        });
    }

    /**
     * Record multiple transactions in bulk (bank-grade performance)
     *
     * Processes multiple double-entry transactions in a single database operation.
     * Uses sequence pooling to minimize database round-trips.
     *
     * @param  array  $transactions  Array of transaction data:
     *                               - correlation_id: string
     *                               - debit_account: string
     *                               - credit_account: string
     *                               - amount: float
     *                               - business: Business
     *                               - description: string|null
     *                               - reference: Model|null
     *                               - metadata: array|null
     *                               - user: User|null
     *                               - currency: string (default: ZAR)
     * @return array Array of results, each with 'debit' and 'credit' entries
     */
    public function recordBulkTransactions(array $transactions): array
    {
        if (empty($transactions)) {
            return [];
        }

        // Use write connection for ledger operations
        return DB::connection('mysql')->transaction(function () use ($transactions) {
            // Pre-allocate sequence numbers for all entries (2 per transaction)
            $totalEntries = count($transactions) * 2;
            $sequenceNumbers = $this->sequenceService->getNextRange($totalEntries);
            $sequenceIndex = 0;

            $entriesToInsert = [];
            $results = [];

            foreach ($transactions as $txn) {
                // Validate required fields
                if (! isset($txn['correlation_id'], $txn['debit_account'], $txn['credit_account'],
                    $txn['amount'], $txn['business'])) {
                    throw new \InvalidArgumentException('Missing required transaction fields');
                }

                $correlationId = $txn['correlation_id'];
                $debitAccount = $txn['debit_account'];
                $creditAccount = $txn['credit_account'];
                $amount = (float) $txn['amount'];
                $business = $txn['business'];
                $description = $txn['description'] ?? null;
                $reference = $txn['reference'] ?? null;
                $metadata = $txn['metadata'] ?? null;
                $user = $txn['user'] ?? null;
                $currency = $txn['currency'] ?? 'ZAR';

                // Validate amount
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Amount must be greater than zero');
                }

                // Validate currency
                if (! isset($this->currencyDivisors[$currency])) {
                    throw new \InvalidArgumentException("Unsupported currency: {$currency}");
                }

                // Validate account types
                $validAccounts = [
                    self::ACCOUNT_ESCROW,
                    self::ACCOUNT_PAYROLL,
                    self::ACCOUNT_PAYMENT,
                    self::ACCOUNT_FEES,
                    self::ACCOUNT_TAXES,
                ];

                if (! in_array($debitAccount, $validAccounts, true)) {
                    throw new \InvalidArgumentException("Invalid debit account type: {$debitAccount}");
                }

                if (! in_array($creditAccount, $validAccounts, true)) {
                    throw new \InvalidArgumentException("Invalid credit account type: {$creditAccount}");
                }

                // Convert to minor units
                $amountMinorUnits = $this->toMinorUnits($amount, $currency);

                // Get sequence numbers from pre-allocated pool
                $debitSequence = $sequenceNumbers[$sequenceIndex++];
                $creditSequence = $sequenceNumbers[$sequenceIndex++];

                $now = now();

                // Prepare debit entry
                $entriesToInsert[] = [
                    'correlation_id' => $correlationId,
                    'sequence_number' => $debitSequence,
                    'transaction_type' => self::TYPE_DEBIT,
                    'account_type' => $debitAccount,
                    'business_id' => $business->id,
                    'reference_type' => $reference ? get_class($reference) : null,
                    'reference_id' => $reference?->id,
                    'amount' => $amount,
                    'amount_minor_units' => $amountMinorUnits,
                    'currency' => $currency,
                    'description' => $description ?? "Debit from {$debitAccount}",
                    'metadata' => $metadata ? json_encode($metadata) : null,
                    'user_id' => $user?->id,
                    'effective_at' => $now,
                    'posting_state' => FinancialLedger::POSTING_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Prepare credit entry
                $entriesToInsert[] = [
                    'correlation_id' => $correlationId,
                    'sequence_number' => $creditSequence,
                    'transaction_type' => self::TYPE_CREDIT,
                    'account_type' => $creditAccount,
                    'business_id' => $business->id,
                    'reference_type' => $reference ? get_class($reference) : null,
                    'reference_id' => $reference?->id,
                    'amount' => $amount,
                    'amount_minor_units' => $amountMinorUnits,
                    'currency' => $currency,
                    'description' => $description ?? "Credit to {$creditAccount}",
                    'metadata' => $metadata ? json_encode($metadata) : null,
                    'user_id' => $user?->id,
                    'effective_at' => $now,
                    'posting_state' => FinancialLedger::POSTING_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Bulk insert all entries in single operation
            if (! empty($entriesToInsert)) {
                // Chunk inserts to avoid query size limits (2000 rows per chunk for better performance)
                // Larger chunks reduce database round-trips while staying within query size limits
                $chunks = array_chunk($entriesToInsert, 2000);
                foreach ($chunks as $chunk) {
                    FinancialLedger::insert($chunk);
                }

                // Reload entries to return full models
                $correlationIds = array_unique(array_column($entriesToInsert, 'correlation_id'));
                $insertedEntries = FinancialLedger::whereIn('correlation_id', $correlationIds)
                    ->orderBy('sequence_number')
                    ->get()
                    ->groupBy('correlation_id');

                // Build results array matching input order
                foreach ($transactions as $txn) {
                    $correlationId = $txn['correlation_id'];
                    $entries = $insertedEntries->get($correlationId);

                    if ($entries) {
                        $debit = $entries->where('transaction_type', self::TYPE_DEBIT)->first();
                        $credit = $entries->where('transaction_type', self::TYPE_CREDIT)->first();

                        $results[] = [
                            'debit' => $debit,
                            'credit' => $credit,
                        ];
                    }
                }
            }

            Log::info('Bulk transactions recorded', [
                'transaction_count' => count($transactions),
                'entry_count' => count($entriesToInsert),
            ]);

            return $results;
        });
    }

    /**
     * Record a simple debit (e.g., escrow deposit)
     *
     * @param  string  $account  Account to debit
     */
    public function recordDebit(
        string $correlationId,
        string $account,
        float $amount,
        Business $business,
        ?string $description = null,
        ?Model $reference = null,
        ?array $metadata = null,
        ?\App\Models\User $user = null
    ): FinancialLedger {
        // For a debit, we credit the source (e.g., BANK) and debit the destination (e.g., ESCROW)
        return $this->recordTransaction(
            $correlationId,
            $account, // Debit account
            self::ACCOUNT_BANK, // Credit account (external source)
            $amount,
            $business,
            $description,
            $reference,
            $metadata,
            $user
        )['debit'];
    }

    /**
     * Record a simple credit (e.g., escrow withdrawal)
     *
     * @param  string  $account  Account to credit
     */
    public function recordCredit(
        string $correlationId,
        string $account,
        float $amount,
        Business $business,
        ?string $description = null,
        ?Model $reference = null,
        ?array $metadata = null,
        ?\App\Models\User $user = null
    ): FinancialLedger {
        // For a credit, we debit the source (e.g., ESCROW) and credit the destination (e.g., BANK)
        return $this->recordTransaction(
            $correlationId,
            self::ACCOUNT_BANK, // Debit account (external destination)
            $account, // Credit account
            $amount,
            $business,
            $description,
            $reference,
            $metadata,
            $user
        )['credit'];
    }

    /**
     * Reverse a transaction by creating opposite entries
     *
     * @param  FinancialLedger  $originalEntry  Original ledger entry to reverse
     * @param  string|null  $reason  Reason for reversal
     * @param  \App\Models\User|null  $user  User initiating the reversal
     * @return array Array with reversed 'debit' and 'credit' entries
     */
    public function reverseTransaction(
        FinancialLedger $originalEntry,
        ?string $reason = null,
        ?\App\Models\User $user = null
    ): array {
        // Find the paired entry (debit/credit pair)
        $pairedEntry = FinancialLedger::where('correlation_id', $originalEntry->correlation_id)
            ->where('id', '!=', $originalEntry->id)
            ->first();

        if (! $pairedEntry) {
            throw new \RuntimeException('Cannot reverse transaction: paired entry not found');
        }

        $correlationId = Str::uuid()->toString();

        return DB::transaction(function () use ($originalEntry, $pairedEntry, $correlationId, $reason, $user) {
            // Validate currency consistency (both entries must have same currency)
            if ($originalEntry->currency !== $pairedEntry->currency) {
                throw new \RuntimeException('Cannot reverse transaction: currency mismatch between entries');
            }

            $currency = $originalEntry->currency;
            $amountMinorUnits = $originalEntry->amount_minor_units ?? $this->toMinorUnits($originalEntry->amount, $currency);

            // Mark original entry as REVERSED
            $originalEntry->update(['posting_state' => FinancialLedger::POSTING_REVERSED]);

            // Generate sequence numbers for reversal entries
            $reversedOriginalSequence = $this->sequenceService->getNext();

            // Reverse the original entry (new entry, starts as PENDING)
            $reversedOriginal = FinancialLedger::create([
                'correlation_id' => $correlationId,
                'sequence_number' => $reversedOriginalSequence,
                'transaction_type' => $originalEntry->isDebit() ? self::TYPE_CREDIT : self::TYPE_DEBIT,
                'account_type' => $originalEntry->account_type,
                'business_id' => $originalEntry->business_id,
                'reference_type' => $originalEntry->reference_type,
                'reference_id' => $originalEntry->reference_id,
                'amount' => $originalEntry->amount,
                'amount_minor_units' => $amountMinorUnits,
                'currency' => $currency,
                'description' => $reason ?? "Reversal of {$originalEntry->description}",
                'metadata' => array_merge($originalEntry->metadata ?? [], [
                    'reversal_reason' => $reason,
                    'original_correlation_id' => $originalEntry->correlation_id,
                ]),
                'reversal_of_id' => $originalEntry->id,
                'user_id' => $user?->id,
                'effective_at' => now(),
                'posting_state' => FinancialLedger::POSTING_PENDING,
            ]);

            // Mark paired entry as REVERSED
            $pairedEntry->update(['posting_state' => FinancialLedger::POSTING_REVERSED]);

            // Reverse the paired entry (same currency, same amount)
            $pairedAmountMinorUnits = $pairedEntry->amount_minor_units ?? $this->toMinorUnits($pairedEntry->amount, $currency);
            $reversedPairedSequence = $this->sequenceService->getNext();

            $reversedPaired = FinancialLedger::create([
                'correlation_id' => $correlationId,
                'sequence_number' => $reversedPairedSequence,
                'transaction_type' => $pairedEntry->isDebit() ? self::TYPE_CREDIT : self::TYPE_DEBIT,
                'account_type' => $pairedEntry->account_type,
                'business_id' => $pairedEntry->business_id,
                'reference_type' => $pairedEntry->reference_type,
                'reference_id' => $pairedEntry->reference_id,
                'amount' => $pairedEntry->amount,
                'amount_minor_units' => $pairedAmountMinorUnits,
                'currency' => $currency, // Same currency as original
                'description' => $reason ?? "Reversal of {$pairedEntry->description}",
                'metadata' => array_merge($pairedEntry->metadata ?? [], [
                    'reversal_reason' => $reason,
                    'original_correlation_id' => $pairedEntry->correlation_id,
                ]),
                'reversal_of_id' => $pairedEntry->id,
                'user_id' => $user?->id,
                'effective_at' => now(),
                'posting_state' => FinancialLedger::POSTING_PENDING,
            ]);

            // Link reversals
            $reversedOriginal->update(['reversed_by_id' => $reversedPaired->id]);
            $reversedPaired->update(['reversed_by_id' => $reversedOriginal->id]);

            Log::info('Transaction reversed', [
                'original_correlation_id' => $originalEntry->correlation_id,
                'reversal_correlation_id' => $correlationId,
                'original_debit_id' => $originalEntry->id,
                'original_credit_id' => $pairedEntry->id,
                'reversed_debit_id' => $reversedOriginal->id,
                'reversed_credit_id' => $reversedPaired->id,
            ]);

            return [
                'debit' => $reversedOriginal->isDebit() ? $reversedOriginal : $reversedPaired,
                'credit' => $reversedOriginal->isCredit() ? $reversedOriginal : $reversedPaired,
            ];
        });
    }

    /**
     * Get balance for an account type for a business from the ledger (source of truth).
     *
     * This is the authoritative balance calculation. All balances should derive from ledger entries.
     * Stored balances in other tables are cached projections for performance.
     *
     * @param  bool  $onlyPosted  If true, only count POSTED entries (default: true for posted balance)
     * @return float Balance from ledger (source of truth)
     */
    public function getAccountBalance(Business $business, string $accountType, bool $onlyPosted = true): float
    {
        $query = FinancialLedger::where('business_id', $business->id)
            ->where('account_type', $accountType)
            ->whereNull('reversal_of_id'); // Exclude reversed entries

        // Only count POSTED entries for posted balance
        if ($onlyPosted) {
            $query->where('posting_state', FinancialLedger::POSTING_POSTED);
        }

        $debits = (clone $query)
            ->where('transaction_type', self::TYPE_DEBIT)
            ->sum('amount');

        $credits = (clone $query)
            ->where('transaction_type', self::TYPE_CREDIT)
            ->sum('amount');

        // For asset accounts (ESCROW), balance = debits - credits
        // For liability accounts, balance = credits - debits
        // For now, we'll use debits - credits for all accounts
        return (float) ($debits - $credits);
    }

    /**
     * Verify that all transactions are balanced (double-entry check)
     * Also validates currency consistency within transactions.
     *
     * @param  Business|null  $business  If null, checks all businesses
     * @return array Array with 'balanced' boolean and 'issues' array
     */
    public function verifyBalances(?Business $business = null): array
    {
        $query = FinancialLedger::query()
            ->whereNull('reversal_of_id'); // Exclude reversed entries

        if ($business) {
            $query->where('business_id', $business->id);
        }

        $entries = $query->get();

        // Group by correlation_id and verify each transaction is balanced
        $correlations = $entries->groupBy('correlation_id');
        $issues = [];

        foreach ($correlations as $correlationId => $correlationEntries) {
            // Check currency consistency (all entries in transaction must have same currency)
            $currencies = $correlationEntries->pluck('currency')->unique();
            if ($currencies->count() > 1) {
                $issues[] = [
                    'correlation_id' => $correlationId,
                    'type' => 'currency_mismatch',
                    'currencies' => $currencies->toArray(),
                    'message' => 'Transaction contains multiple currencies - cross-currency transactions forbidden',
                ];
            }

            $debitTotal = $correlationEntries->where('transaction_type', self::TYPE_DEBIT)->sum('amount');
            $creditTotal = $correlationEntries->where('transaction_type', self::TYPE_CREDIT)->sum('amount');

            if (abs($debitTotal - $creditTotal) > 0.01) {
                $issues[] = [
                    'correlation_id' => $correlationId,
                    'type' => 'unbalanced',
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'difference' => abs($debitTotal - $creditTotal),
                ];
            }
        }

        return [
            'balanced' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Generate a correlation ID for a transaction
     */
    public function generateCorrelationId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Generate next sequence number (global, monotonic, gap-tolerant)
     * Sequence is monotonic and gap-tolerant (allows for future inserts)
     * Uses atomic sequence service for bank-grade performance
     *
     * @deprecated Use SequenceService directly for better performance
     */
    protected function generateSequenceNumber(string $accountType, int $businessId): int
    {
        // Use atomic sequence service instead of MAX() query
        return $this->sequenceService->getNext();
    }

    /**
     * Replay account entries from a specific sequence number
     * Useful for audit and dispute resolution
     *
     * @param  int|null  $fromSequence  Start from this sequence (null = from beginning)
     * @return \Illuminate\Support\Collection Collection of ledger entries in sequence order
     */
    public function replayAccount(Business $business, string $accountType, ?int $fromSequence = null): \Illuminate\Support\Collection
    {
        $query = FinancialLedger::where('business_id', $business->id)
            ->where('account_type', $accountType)
            ->whereNull('reversal_of_id') // Exclude reversed entries
            ->orderBy('sequence_number', 'asc');

        if ($fromSequence !== null) {
            $query->where('sequence_number', '>=', $fromSequence);
        }

        return $query->get();
    }

    /**
     * Post pending ledger entries (transition from PENDING to POSTED)
     * Called after settlement window processing
     *
     * @param  string  $correlationId  Correlation ID of transaction to post
     * @return bool Success
     */
    public function postTransaction(string $correlationId): bool
    {
        $entries = FinancialLedger::where('correlation_id', $correlationId)
            ->where('posting_state', FinancialLedger::POSTING_PENDING)
            ->get();

        if ($entries->isEmpty()) {
            return false;
        }

        $businessIds = [];
        foreach ($entries as $entry) {
            $entry->markAsPosted();
            if ($entry->business_id) {
                $businessIds[] = $entry->business_id;
            }
        }

        // Invalidate balance cache for affected businesses after posting
        // Posting changes the balance calculation (only POSTED entries count)
        if (! empty($businessIds)) {
            $uniqueBusinessIds = array_unique($businessIds);
            $this->getBalancePrecalculationService()->invalidateCacheBulk($uniqueBusinessIds);
        }

        LogContext::info('Transaction posted', LogContext::create(
            $correlationId,
            $entries->first()?->business_id ?? null,
            null,
            'ledger_post',
            null,
            ['entries_count' => $entries->count()]
        ));

        return true;
    }

    /**
     * Post multiple transactions in bulk (for settlement windows)
     *
     * Ensures all entries in each correlation_id are posted atomically.
     * Validates that all entries in a transaction are posted together.
     *
     * @param  array  $correlationIds  Array of correlation IDs to post
     * @param  int  $maxRetries  Maximum number of retries for posting failures
     * @return array Results with 'posted', 'failed', 'errors'
     */
    public function postBulkTransactions(array $correlationIds, int $maxRetries = 3): array
    {
        if (empty($correlationIds)) {
            return [
                'posted' => 0,
                'failed' => 0,
                'errors' => [],
            ];
        }

        $posted = 0;
        $failed = 0;
        $errors = [];

        // Group by correlation_id to ensure atomic posting per transaction
        $correlationIds = array_unique($correlationIds);

        foreach ($correlationIds as $correlationId) {
            $retries = 0;
            $success = false;

            while ($retries < $maxRetries && ! $success) {
                try {
                    $success = DB::transaction(function () use ($correlationId, &$posted) {
                        // Get all pending entries for this correlation_id
                        $entries = FinancialLedger::where('correlation_id', $correlationId)
                            ->where('posting_state', FinancialLedger::POSTING_PENDING)
                            ->lockForUpdate()
                            ->get();

                        if ($entries->isEmpty()) {
                            // Already posted or doesn't exist - not an error
                            return true;
                        }

                        // Validate that we have both debit and credit entries (double-entry check)
                        $debitCount = $entries->where('transaction_type', self::TYPE_DEBIT)->count();
                        $creditCount = $entries->where('transaction_type', self::TYPE_CREDIT)->count();

                        if ($debitCount === 0 || $creditCount === 0) {
                            throw new \RuntimeException("Invalid transaction: missing debit or credit entries for correlation_id {$correlationId}");
                        }

                        // Validate amounts balance (double-entry check)
                        $debitTotal = $entries->where('transaction_type', self::TYPE_DEBIT)->sum('amount');
                        $creditTotal = $entries->where('transaction_type', self::TYPE_CREDIT)->sum('amount');

                        if (abs($debitTotal - $creditTotal) > 0.01) {
                            throw new \RuntimeException("Unbalanced transaction: debit total ({$debitTotal}) != credit total ({$creditTotal}) for correlation_id {$correlationId}");
                        }

                        // Post all entries atomically
                        $now = now();
                        $businessIds = [];
                        foreach ($entries as $entry) {
                            $entry->update([
                                'posting_state' => FinancialLedger::POSTING_POSTED,
                                'posted_at' => $now,
                            ]);
                            if ($entry->business_id) {
                                $businessIds[] = $entry->business_id;
                            }
                        }

                        $posted += $entries->count();

                        // Invalidate balance cache for affected businesses after posting
                        // Posting changes the balance calculation (only POSTED entries count)
                        if (! empty($businessIds)) {
                            $uniqueBusinessIds = array_unique($businessIds);
                            $this->getBalancePrecalculationService()->invalidateCacheBulk($uniqueBusinessIds);
                        }

                        LogContext::debug('Transaction posted in bulk', LogContext::create(
                            $correlationId,
                            $entries->first()?->business_id,
                            null,
                            'ledger_post_bulk',
                            null,
                            ['entries_count' => $entries->count()]
                        ));

                        return true;
                    }, $maxRetries);

                    if ($success) {
                        break; // Success - exit retry loop
                    }
                } catch (\Exception $e) {
                    $retries++;

                    if ($retries >= $maxRetries) {
                        // Max retries exceeded
                        $failed++;
                        $errors[] = [
                            'correlation_id' => $correlationId,
                            'error' => $e->getMessage(),
                            'retries' => $retries,
                        ];

                        LogContext::error('Failed to post transaction after retries', LogContext::create(
                            $correlationId,
                            null,
                            null,
                            'ledger_post_bulk',
                            null,
                            [
                                'error' => $e->getMessage(),
                                'retries' => $retries,
                            ]
                        ));
                    } else {
                        // Retry with exponential backoff
                        $delay = min(100 * pow(2, $retries - 1), 1000); // Max 1 second
                        usleep($delay * 1000); // Convert to microseconds

                        LogContext::warning('Retrying transaction post', LogContext::create(
                            $correlationId,
                            null,
                            null,
                            'ledger_post_bulk',
                            null,
                            [
                                'attempt' => $retries + 1,
                                'max_retries' => $maxRetries,
                                'error' => $e->getMessage(),
                            ]
                        ));
                    }
                }
            }
        }

        LogContext::info('Bulk transaction posting completed', LogContext::create(
            null,
            null,
            null,
            'ledger_post_bulk',
            null,
            [
                'total_correlation_ids' => count($correlationIds),
                'posted' => $posted,
                'failed' => $failed,
            ]
        ));

        return [
            'posted' => $posted,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
