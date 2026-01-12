<?php

namespace App\Rules;

use App\Services\SouthAfricaHolidayService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BusinessDay implements ValidationRule
{
    public function __construct(
        protected SouthAfricaHolidayService $holidayService
    ) {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = new \DateTime($value);
        } catch (\Exception $e) {
            $fail('The :attribute must be a valid date.');
            return;
        }

        if ($this->holidayService->isWeekend($date)) {
            $fail('The :attribute cannot be on a weekend.');
            return;
        }

        if ($this->holidayService->isHoliday($date)) {
            $holidayName = $this->holidayService->getHolidayName($date);
            $fail("The :attribute cannot be on {$holidayName}.");
        }
    }
}
