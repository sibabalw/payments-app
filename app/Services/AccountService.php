<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Business;
use Illuminate\Support\Facades\Log;

class AccountService
{
    /**
     * Create or get system account
     */
    public function getOrCreateSystemAccount(string $category, string $currency = 'ZAR'): Account
    {
        $account = Account::getSystemAccount($category, $currency);

        if ($account) {
            return $account;
        }

        // Determine account type based on category
        $type = $this->getAccountTypeForCategory($category);

        return Account::create([
            'code' => $this->generateSystemAccountCode($category, $currency),
            'name' => $this->getAccountNameForCategory($category),
            'type' => $type,
            'category' => $category,
            'owner_type' => null, // System account
            'owner_id' => null,
            'currency' => $currency,
            'is_system_account' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create or get business account
     */
    public function getOrCreateBusinessAccount(Business $business, string $category, string $currency = 'ZAR'): Account
    {
        $account = Account::getBusinessAccount($business->id, $category, $currency);

        if ($account) {
            return $account;
        }

        // Determine account type based on category
        $type = $this->getAccountTypeForCategory($category);

        return Account::create([
            'code' => $this->generateBusinessAccountCode($business->id, $category, $currency),
            'name' => $this->getAccountNameForCategory($category)." - {$business->name}",
            'type' => $type,
            'category' => $category,
            'owner_type' => Business::class,
            'owner_id' => $business->id,
            'currency' => $currency,
            'is_system_account' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Get account type for category
     */
    protected function getAccountTypeForCategory(string $category): string
    {
        return match ($category) {
            'ESCROW', 'BANK' => 'ASSET',
            'PAYROLL', 'PAYMENT' => 'EXPENSE',
            'FEES' => 'REVENUE',
            'TAX' => 'LIABILITY',
            default => 'ASSET',
        };
    }

    /**
     * Generate system account code
     */
    protected function generateSystemAccountCode(string $category, string $currency): string
    {
        return strtoupper("{$category}_SYSTEM_{$currency}");
    }

    /**
     * Generate business account code
     */
    protected function generateBusinessAccountCode(int $businessId, string $category, string $currency): string
    {
        return strtoupper("{$category}_{$businessId}_{$currency}");
    }

    /**
     * Get account name for category
     */
    protected function getAccountNameForCategory(string $category): string
    {
        return match ($category) {
            'ESCROW' => 'Escrow Account',
            'BANK' => 'Bank Account',
            'PAYROLL' => 'Payroll Account',
            'PAYMENT' => 'Payment Account',
            'FEES' => 'Fees Account',
            'TAX' => 'Tax Account',
            default => ucfirst(strtolower($category)).' Account',
        };
    }

    /**
     * Initialize default accounts for a business
     */
    public function initializeBusinessAccounts(Business $business, string $currency = 'ZAR'): array
    {
        $accounts = [];

        $categories = ['ESCROW', 'PAYROLL', 'PAYMENT'];

        foreach ($categories as $category) {
            $accounts[$category] = $this->getOrCreateBusinessAccount($business, $category, $currency);
        }

        Log::info('Business accounts initialized', [
            'business_id' => $business->id,
            'currency' => $currency,
            'accounts' => array_keys($accounts),
        ]);

        return $accounts;
    }
}
