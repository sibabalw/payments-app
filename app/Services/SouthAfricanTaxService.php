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
     * UIF earnings ceiling per month (as of 1 June 2021)
     * Contributions are calculated only on remuneration up to this amount
     */
    private const UIF_EARNINGS_CEILING = 17712.00;

    /**
     * UIF maximum contribution per month (1% of earnings ceiling)
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
     * @param  float  $grossSalary  Monthly gross salary
     * @param  int|null  $taxYear  Tax year (defaults to current year)
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
     * According to SARS: 1% of remuneration, capped at R17,712/month earnings ceiling
     * Maximum employee contribution is R177.12/month (1% of R17,712)
     *
     * Exemptions: Employees working fewer than 24 hours per month are exempt from UIF
     *
     * @param  float  $grossSalary  Monthly gross salary
     * @param  bool  $isExempt  Whether employee is exempt from UIF (e.g., works < 24 hours/month)
     * @return float Monthly UIF amount (0 if exempt, otherwise capped at maximum)
     */
    public function calculateUIF(float $grossSalary, bool $isExempt = false): float
    {
        // If employee is exempt (e.g., works < 24 hours/month), no UIF contribution
        if ($isExempt) {
            return 0;
        }

        // UIF is calculated on remuneration up to the earnings ceiling only
        $uifBase = min($grossSalary, self::UIF_EARNINGS_CEILING);
        $uifAmount = $uifBase * self::UIF_RATE;

        return round($uifAmount, 2);
    }

    /**
     * Calculate SDL (Skills Development Levy)
     * According to SARS: 1% of leviable payroll, paid ENTIRELY by employer (NOT deducted from employee)
     * Only applies if annual payroll exceeds R500,000
     *
     * @param  float  $grossSalary  Monthly gross salary
     * @param  bool  $employerPays  Whether employer pays SDL (default true)
     * @param  float|null  $annualPayroll  Total annual payroll for the business (to check threshold)
     * @return float Monthly SDL amount (0 if below threshold)
     *
     * @note SDL is NOT deducted from employee salary - it's an employer cost only
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

        if (! $employerPays) {
            return 0;
        }

        $sdlAmount = $grossSalary * self::SDL_RATE;

        return round($sdlAmount, 2);
    }

    /**
     * Calculate net salary with full tax breakdown (statutory deductions only)
     *
     * This method calculates net salary after applying only statutory deductions (PAYE, UIF).
     * Adjustments (deductions/additions) are applied separately after this calculation.
     *
     * @param  float  $grossSalary  Monthly gross salary
     * @param  array  $options  Additional options:
     *                          - 'tax_year' (int): Tax year for PAYE calculation
     *                          - 'employer_pays_sdl' (bool): Whether employer pays SDL
     *                          - 'annual_payroll' (float): Total annual payroll for SDL threshold check
     *                          - 'uif_exempt' (bool): Whether employee is exempt from UIF
     * @return array Breakdown with gross, paye, uif, sdl, net, and total_deductions
     */
    public function calculateNetSalary(float $grossSalary, array $options = []): array
    {
        $taxYear = $options['tax_year'] ?? null;
        $employerPaysSDL = $options['employer_pays_sdl'] ?? true;
        $annualPayroll = $options['annual_payroll'] ?? null;
        $uifExempt = $options['uif_exempt'] ?? false;

        $paye = $this->calculatePAYE($grossSalary, $taxYear);
        $uif = $this->calculateUIF($grossSalary, $uifExempt);
        $sdl = $this->calculateSDL($grossSalary, $employerPaysSDL, $annualPayroll);

        // SDL is NOT deducted from employee salary - it's paid entirely by the employer
        // Only PAYE and UIF reduce the employee's net salary
        $totalEmployeeDeductions = $paye + $uif;
        $netSalary = $grossSalary - $totalEmployeeDeductions;

        return [
            'gross' => round($grossSalary, 2),
            'paye' => $paye,
            'uif' => $uif,
            'sdl' => $sdl, // Employer cost only - NOT deducted from employee
            'net' => round($netSalary, 2),
            'total_deductions' => round($totalEmployeeDeductions, 2), // Only employee statutory deductions
            'total_employer_costs' => round($sdl, 2), // SDL is employer cost
        ];
    }
}
