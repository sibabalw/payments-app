<?php

namespace App\Services;

class SouthAfricanTaxService
{
    /**
     * Annual tax thresholds and rates for 2024/2025 tax year
     */
    private const TAX_BRACKETS = [
        ['min' => 0, 'max' => 237100, 'rate' => 0.18, 'base' => 0],
        ['min' => 237101, 'max' => 370500, 'rate' => 0.26, 'base' => 42678],
        ['min' => 370501, 'max' => 512800, 'rate' => 0.31, 'base' => 77362],
        ['min' => 512801, 'max' => 673000, 'rate' => 0.36, 'base' => 121475],
        ['min' => 673001, 'max' => 857900, 'rate' => 0.39, 'base' => 179147],
        ['min' => 857901, 'max' => 1817000, 'rate' => 0.41, 'base' => 251258],
        ['min' => 1817001, 'max' => PHP_INT_MAX, 'rate' => 0.45, 'base' => 644489],
    ];

    /**
     * Primary rebate for 2024/2025 tax year
     */
    private const PRIMARY_REBATE = 17235;

    /**
     * UIF contribution rate (employee)
     */
    private const UIF_RATE = 0.01;

    /**
     * UIF maximum contribution per month
     */
    private const UIF_MAX_MONTHLY = 177.12;

    /**
     * SDL rate (employer contribution)
     */
    private const SDL_RATE = 0.01;

    /**
     * SDL threshold - only applies if annual payroll exceeds this amount
     */
    private const SDL_THRESHOLD = 500000;

    /**
     * Calculate PAYE (Pay As You Earn) tax for a given gross salary
     *
     * @param float $grossSalary Monthly gross salary
     * @param int|null $taxYear Tax year (defaults to current year)
     * @return float Monthly PAYE amount
     */
    public function calculatePAYE(float $grossSalary, ?int $taxYear = null): float
    {
        if ($taxYear === null) {
            $taxYear = (int) date('Y');
        }

        // Convert monthly to annual for tax calculation
        $annualSalary = $grossSalary * 12;

        // Find the applicable tax bracket
        $taxBracket = null;
        foreach (self::TAX_BRACKETS as $bracket) {
            if ($annualSalary >= $bracket['min'] && $annualSalary <= $bracket['max']) {
                $taxBracket = $bracket;
                break;
            }
        }

        if ($taxBracket === null) {
            // Use highest bracket if salary exceeds all brackets
            $taxBracket = end(self::TAX_BRACKETS);
        }

        // Calculate annual tax
        $taxableAmount = $annualSalary - $taxBracket['min'];
        $annualTax = $taxBracket['base'] + ($taxableAmount * $taxBracket['rate']);

        // Apply primary rebate
        $annualTaxAfterRebate = max(0, $annualTax - self::PRIMARY_REBATE);

        // Convert back to monthly
        return round($annualTaxAfterRebate / 12, 2);
    }

    /**
     * Calculate UIF (Unemployment Insurance Fund) contribution
     *
     * @param float $grossSalary Monthly gross salary
     * @return float Monthly UIF amount (capped at maximum)
     */
    public function calculateUIF(float $grossSalary): float
    {
        $uifAmount = $grossSalary * self::UIF_RATE;
        return round(min($uifAmount, self::UIF_MAX_MONTHLY), 2);
    }

    /**
     * Calculate SDL (Skills Development Levy)
     *
     * @param float $grossSalary Monthly gross salary
     * @param bool $employerPays Whether employer pays SDL (default true)
     * @param float|null $annualPayroll Total annual payroll for the business (to check threshold)
     * @return float Monthly SDL amount (0 if below threshold)
     */
    public function calculateSDL(float $grossSalary, bool $employerPays = true, ?float $annualPayroll = null): float
    {
        // SDL only applies if annual payroll exceeds threshold
        if ($annualPayroll !== null && $annualPayroll < self::SDL_THRESHOLD) {
            return 0;
        }

        // If annual payroll not provided, assume it applies (can be overridden)
        if ($annualPayroll === null) {
            // Default to applying SDL
        }

        if (!$employerPays) {
            return 0;
        }

        $sdlAmount = $grossSalary * self::SDL_RATE;
        return round($sdlAmount, 2);
    }

    /**
     * Calculate net salary with full tax breakdown
     *
     * @param float $grossSalary Monthly gross salary
     * @param array $options Additional options:
     *   - 'tax_year' (int): Tax year for PAYE calculation
     *   - 'employer_pays_sdl' (bool): Whether employer pays SDL
     *   - 'annual_payroll' (float): Total annual payroll for SDL threshold check
     * @return array Breakdown with gross, paye, uif, sdl, net, and total_deductions
     */
    public function calculateNetSalary(float $grossSalary, array $options = []): array
    {
        $taxYear = $options['tax_year'] ?? null;
        $employerPaysSDL = $options['employer_pays_sdl'] ?? true;
        $annualPayroll = $options['annual_payroll'] ?? null;

        $paye = $this->calculatePAYE($grossSalary, $taxYear);
        $uif = $this->calculateUIF($grossSalary);
        $sdl = $this->calculateSDL($grossSalary, $employerPaysSDL, $annualPayroll);

        $totalDeductions = $paye + $uif + $sdl;
        $netSalary = $grossSalary - $totalDeductions;

        return [
            'gross' => round($grossSalary, 2),
            'paye' => $paye,
            'uif' => $uif,
            'sdl' => $sdl,
            'net' => round($netSalary, 2),
            'total_deductions' => round($totalDeductions, 2),
        ];
    }
}
