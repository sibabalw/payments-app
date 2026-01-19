<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmployeeOtp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $employeeId = $request->session()->get('employee_verified_id');
        $verifiedAt = $request->session()->get('employee_verified_at');

        // Check if employee is verified
        if (! $employeeId || ! $verifiedAt) {
            return redirect()->route('employee.sign-in')
                ->with('error', 'Please verify your email to continue.');
        }

        // Check if session has expired (24 hours)
        $expiresAt = $verifiedAt + (24 * 60 * 60); // 24 hours in seconds
        if (now()->timestamp > $expiresAt) {
            $request->session()->forget(['employee_verified_id', 'employee_verified_at', 'employee_verified_email']);

            return redirect()->route('employee.sign-in')
                ->with('error', 'Your session has expired. Please verify your email again.');
        }

        // Verify employee still exists
        $employee = Employee::find($employeeId);
        if (! $employee) {
            $request->session()->forget(['employee_verified_id', 'employee_verified_at', 'employee_verified_email']);

            return redirect()->route('employee.sign-in')
                ->with('error', 'Employee not found. Please contact your administrator.');
        }

        // Share employee with request for use in controllers
        $request->merge(['employee' => $employee]);

        return $next($request);
    }
}
