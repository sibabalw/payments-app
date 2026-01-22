<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ComplianceSubmission;
use App\Models\Employee;
use App\Services\EMP201Service;
use App\Services\IRP5Service;
use App\Services\UIFDeclarationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceController extends Controller
{
    public function __construct(
        private readonly UIFDeclarationService $uifService,
        private readonly EMP201Service $emp201Service,
        private readonly IRP5Service $irp5Service
    ) {}

    /**
     * Compliance dashboard overview
     */
    public function index(): Response
    {
        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = $businessId ? Business::find($businessId) : null;

        // Get compliance summary
        $currentMonth = now()->format('Y-m');
        $currentTaxYear = $this->irp5Service->getTaxYear();

        $submissions = [];
        $pendingItems = [];

        if ($business) {
            // Get recent submissions
            $submissions = ComplianceSubmission::where('business_id', $business->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'type' => $submission->type,
                        'type_display' => $this->getTypeDisplay($submission->type),
                        'period' => $submission->period,
                        'status' => $submission->status,
                        'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i'),
                        'created_at' => $submission->created_at->format('Y-m-d H:i'),
                    ];
                });

            // Check for pending submissions
            $pendingUI19 = $this->uifService->getPendingPeriods($business);
            $pendingEMP201 = $this->emp201Service->getPendingPeriods($business);

            if ($pendingUI19->isNotEmpty()) {
                $pendingItems[] = [
                    'type' => 'ui19',
                    'title' => 'UIF Declarations',
                    'count' => $pendingUI19->count(),
                    'periods' => $pendingUI19->take(3)->toArray(),
                ];
            }

            if ($pendingEMP201->isNotEmpty()) {
                $pendingItems[] = [
                    'type' => 'emp201',
                    'title' => 'EMP201 Submissions',
                    'count' => $pendingEMP201->count(),
                    'periods' => $pendingEMP201->take(3)->toArray(),
                ];
            }
        }

        // Get upcoming deadlines
        $deadlines = $this->getUpcomingDeadlines();

        return Inertia::render('compliance/index', [
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'tax_id' => $business->tax_id,
                'registration_number' => $business->registration_number,
            ] : null,
            'submissions' => $submissions,
            'pendingItems' => $pendingItems,
            'deadlines' => $deadlines,
            'currentTaxYear' => $currentTaxYear,
            'currentMonth' => $currentMonth,
        ]);
    }

    /**
     * UIF declarations page
     */
    public function uifIndex(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = $businessId ? Business::find($businessId) : null;

        $submissions = [];
        $pendingPeriods = [];
        $previewData = null;

        if ($business) {
            // Get existing UI-19 submissions
            $submissions = ComplianceSubmission::where('business_id', $business->id)
                ->where('type', 'ui19')
                ->orderBy('period', 'desc')
                ->get()
                ->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'period' => $submission->period,
                        'period_display' => Carbon::createFromFormat('Y-m', $submission->period)->format('F Y'),
                        'status' => $submission->status,
                        'data' => $submission->data,
                        'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i'),
                        'created_at' => $submission->created_at->format('Y-m-d H:i'),
                    ];
                });

            // Get pending periods
            $pendingPeriods = $this->uifService->getPendingPeriods($business)->map(function ($period) {
                return [
                    'value' => $period,
                    'label' => Carbon::createFromFormat('Y-m', $period)->format('F Y'),
                ];
            });

            // If a period is requested, generate preview data
            $selectedPeriod = $request->get('period');
            if ($selectedPeriod) {
                $previewData = $this->uifService->generateMonthlyUI19($business, $selectedPeriod);
            }
        }

        return Inertia::render('compliance/uif/index', [
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
            ] : null,
            'submissions' => $submissions,
            'pendingPeriods' => $pendingPeriods,
            'previewData' => $previewData,
            'selectedPeriod' => $request->get('period'),
        ]);
    }

    /**
     * Generate UI-19 declaration
     */
    public function generateUI19(Request $request)
    {
        $request->validate([
            'period' => 'required|date_format:Y-m',
        ]);

        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = Business::findOrFail($businessId);

        $data = $this->uifService->generateMonthlyUI19($business, $request->period);
        $this->uifService->saveUI19Submission($business, $request->period, $data);

        return redirect()->route('compliance.uif.index')
            ->with('success', 'UI-19 declaration generated successfully.');
    }

    /**
     * Download UI-19 as CSV
     */
    public function downloadUI19(ComplianceSubmission $submission): StreamedResponse
    {
        $this->authorizeSubmission($submission);

        $content = $this->uifService->generateUI19Csv($submission->data);
        $filename = "ui19_{$submission->period}.csv";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Edit UI-19 submission
     */
    public function editUI19(ComplianceSubmission $submission): Response
    {
        $this->authorizeSubmission($submission);

        if ($submission->type !== 'ui19') {
            abort(404, 'Invalid submission type.');
        }

        return Inertia::render('compliance/uif/edit', [
            'submission' => [
                'id' => $submission->id,
                'period' => $submission->period,
                'period_display' => Carbon::createFromFormat('Y-m', $submission->period)->format('F Y'),
                'status' => $submission->status,
                'data' => $submission->data,
            ],
        ]);
    }

    /**
     * Update UI-19 submission
     */
    public function updateUI19(Request $request, ComplianceSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        if ($submission->type !== 'ui19') {
            abort(404, 'Invalid submission type.');
        }

        if ($submission->status === 'submitted') {
            return back()->with('error', 'Cannot edit a submitted declaration.');
        }

        $request->validate([
            'data' => 'required|array',
            'data.employees' => 'required|array',
            'data.totals' => 'required|array',
        ]);

        $submission->update([
            'data' => $request->data,
        ]);

        return redirect()->route('compliance.uif.index')
            ->with('success', 'UI-19 declaration updated successfully.');
    }

    /**
     * EMP201 submissions page
     */
    public function emp201Index(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = $businessId ? Business::find($businessId) : null;

        $submissions = [];
        $pendingPeriods = [];
        $previewData = null;
        $checklist = [];

        if ($business) {
            // Get existing EMP201 submissions
            $submissions = ComplianceSubmission::where('business_id', $business->id)
                ->where('type', 'emp201')
                ->orderBy('period', 'desc')
                ->get()
                ->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'period' => $submission->period,
                        'period_display' => Carbon::createFromFormat('Y-m', $submission->period)->format('F Y'),
                        'status' => $submission->status,
                        'data' => $submission->data,
                        'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i'),
                        'created_at' => $submission->created_at->format('Y-m-d H:i'),
                    ];
                });

            // Get pending periods
            $pendingPeriods = $this->emp201Service->getPendingPeriods($business)->map(function ($period) {
                return [
                    'value' => $period,
                    'label' => Carbon::createFromFormat('Y-m', $period)->format('F Y'),
                ];
            });

            // If a period is requested, generate preview data
            $selectedPeriod = $request->get('period');
            if ($selectedPeriod) {
                $previewData = $this->emp201Service->generateEMP201($business, $selectedPeriod);
                $checklist = $this->emp201Service->getSubmissionChecklist($previewData);
            }
        }

        return Inertia::render('compliance/emp201/index', [
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'tax_id' => $business->tax_id,
            ] : null,
            'submissions' => $submissions,
            'pendingPeriods' => $pendingPeriods,
            'previewData' => $previewData,
            'checklist' => $checklist,
            'selectedPeriod' => $request->get('period'),
        ]);
    }

    /**
     * Generate EMP201 submission
     */
    public function generateEMP201(Request $request)
    {
        $request->validate([
            'period' => 'required|date_format:Y-m',
        ]);

        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = Business::findOrFail($businessId);

        $data = $this->emp201Service->generateEMP201($business, $request->period);
        $this->emp201Service->saveEMP201Submission($business, $request->period, $data);

        return redirect()->route('compliance.emp201.index')
            ->with('success', 'EMP201 data generated successfully.');
    }

    /**
     * Download EMP201 as CSV
     */
    public function downloadEMP201(ComplianceSubmission $submission): StreamedResponse
    {
        $this->authorizeSubmission($submission);

        $content = $this->emp201Service->generateEMP201Csv($submission->data);
        $filename = "emp201_{$submission->period}.csv";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Edit EMP201 submission
     */
    public function editEMP201(ComplianceSubmission $submission): Response
    {
        $this->authorizeSubmission($submission);

        if ($submission->type !== 'emp201') {
            abort(404, 'Invalid submission type.');
        }

        return Inertia::render('compliance/emp201/edit', [
            'submission' => [
                'id' => $submission->id,
                'period' => $submission->period,
                'period_display' => Carbon::createFromFormat('Y-m', $submission->period)->format('F Y'),
                'status' => $submission->status,
                'data' => $submission->data,
            ],
        ]);
    }

    /**
     * Update EMP201 submission
     */
    public function updateEMP201(Request $request, ComplianceSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        if ($submission->type !== 'emp201') {
            abort(404, 'Invalid submission type.');
        }

        if ($submission->status === 'submitted') {
            return back()->with('error', 'Cannot edit a submitted submission.');
        }

        $request->validate([
            'data' => 'required|array',
            'data.employees' => 'required|array',
            'data.totals' => 'required|array',
        ]);

        $submission->update([
            'data' => $request->data,
        ]);

        return redirect()->route('compliance.emp201.index')
            ->with('success', 'EMP201 data updated successfully.');
    }

    /**
     * IRP5 certificates page
     */
    public function irp5Index(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = $businessId ? Business::find($businessId) : null;

        $employees = [];
        $taxYears = [];
        $selectedTaxYear = $request->get('tax_year', $this->irp5Service->getTaxYear());
        $generatedCount = 0;
        $pendingCount = 0;

        if ($business) {
            // Get available tax years
            $taxYears = $this->irp5Service->getAvailableTaxYears($business)->map(function ($year) {
                return [
                    'value' => $year,
                    'label' => $year,
                ];
            });

            // Get employees with IRP5 status
            $employees = $this->irp5Service->getEmployeesWithIRP5Status($business, $selectedTaxYear);

            // Pre-compute counts at SQL level to avoid frontend aggregation
            foreach ($employees as $emp) {
                $status = $emp['irp5_status'] ?? 'pending';
                if ($status === 'generated' || $status === 'submitted') {
                    $generatedCount++;
                } elseif ($status === 'pending') {
                    $pendingCount++;
                }
            }
        }

        return Inertia::render('compliance/irp5/index', [
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
            ] : null,
            'employees' => $employees,
            'taxYears' => $taxYears,
            'selectedTaxYear' => $selectedTaxYear,
            'generatedCount' => $generatedCount,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Generate IRP5 for an employee
     */
    public function generateIRP5(Request $request, Employee $employee)
    {
        $request->validate([
            'tax_year' => 'required|string',
        ]);

        $data = $this->irp5Service->generateIRP5($employee, $request->tax_year);

        if (isset($data['error'])) {
            return back()->with('error', $data['error']);
        }

        $this->irp5Service->saveIRP5Submission($employee, $request->tax_year, $data);

        return back()->with('success', 'IRP5 certificate generated successfully.');
    }

    /**
     * Generate bulk IRP5 certificates
     */
    public function generateBulkIRP5(Request $request)
    {
        $request->validate([
            'tax_year' => 'required|string',
        ]);

        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = Business::findOrFail($businessId);

        $certificates = $this->irp5Service->generateBulkIRP5($business, $request->tax_year);

        // Save all certificates
        $saved = 0;
        foreach ($certificates as $index => $data) {
            if (! isset($data['error'])) {
                $employee = Employee::find($data['employee']['id'] ?? null);
                if ($employee || isset($data['employee']['name'])) {
                    // Find employee by data
                    $employeeId = Employee::where('name', $data['employee']['name'])
                        ->where('business_id', $business->id)
                        ->value('id');

                    if ($employeeId) {
                        $employee = Employee::find($employeeId);
                        $this->irp5Service->saveIRP5Submission($employee, $request->tax_year, $data);
                        $saved++;
                    }
                }
            }
        }

        return back()->with('success', "{$saved} IRP5 certificates generated successfully.");
    }

    /**
     * Download IRP5 as PDF
     */
    public function downloadIRP5(ComplianceSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        $pdf = $this->irp5Service->generateIRP5Pdf($submission->data);
        $filename = "irp5_{$submission->period}_{$submission->employee_id}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Edit IRP5 submission
     */
    public function editIRP5(ComplianceSubmission $submission): Response
    {
        $this->authorizeSubmission($submission);

        if ($submission->type !== 'irp5') {
            abort(404, 'Invalid submission type.');
        }

        return Inertia::render('compliance/irp5/edit', [
            'submission' => [
                'id' => $submission->id,
                'period' => $submission->period,
                'employee_id' => $submission->employee_id,
                'status' => $submission->status,
                'data' => $submission->data,
            ],
        ]);
    }

    /**
     * Update IRP5 submission
     */
    public function updateIRP5(Request $request, ComplianceSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        if ($submission->type !== 'irp5') {
            abort(404, 'Invalid submission type.');
        }

        if ($submission->status === 'submitted') {
            return back()->with('error', 'Cannot edit a submitted certificate.');
        }

        $request->validate([
            'data' => 'required|array',
            'data.employee' => 'required|array',
            'data.income' => 'required|array',
            'data.deductions' => 'required|array',
        ]);

        $submission->update([
            'data' => $request->data,
        ]);

        return redirect()->route('compliance.irp5.index')
            ->with('success', 'IRP5 certificate updated successfully.');
    }

    /**
     * SARS export page
     */
    public function sarsExport(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->current_business_id ?? session('current_business_id');
        $business = $businessId ? Business::find($businessId) : null;

        $submissions = [];

        if ($business) {
            $submissions = ComplianceSubmission::where('business_id', $business->id)
                ->whereIn('status', ['generated', 'submitted'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'type' => $submission->type,
                        'type_display' => $this->getTypeDisplay($submission->type),
                        'period' => $submission->period,
                        'status' => $submission->status,
                        'created_at' => $submission->created_at->format('Y-m-d H:i'),
                    ];
                });
        }

        return Inertia::render('compliance/sars-export', [
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
            ] : null,
            'submissions' => $submissions,
        ]);
    }

    /**
     * Mark submission as submitted
     */
    public function markSubmitted(ComplianceSubmission $submission)
    {
        $this->authorizeSubmission($submission);

        $submission->markAsSubmitted();

        return back()->with('success', 'Submission marked as submitted to SARS.');
    }

    /**
     * Get type display name
     */
    private function getTypeDisplay(string $type): string
    {
        return match ($type) {
            'ui19' => 'UIF UI-19',
            'emp201' => 'EMP201',
            'irp5' => 'IRP5',
            default => strtoupper($type),
        };
    }

    /**
     * Get upcoming compliance deadlines
     */
    private function getUpcomingDeadlines(): array
    {
        $now = now();

        return [
            [
                'title' => 'EMP201 Monthly Submission',
                'description' => 'PAYE, UIF, and SDL for '.$now->format('F Y'),
                'deadline' => $now->copy()->addMonth()->day(7)->format('Y-m-d'),
                'type' => 'emp201',
            ],
            [
                'title' => 'UIF Monthly Declaration',
                'description' => 'UI-19 for '.$now->format('F Y'),
                'deadline' => $now->copy()->addMonth()->day(7)->format('Y-m-d'),
                'type' => 'ui19',
            ],
            [
                'title' => 'IRP5/IT3(a) Annual Submission',
                'description' => 'Tax year '.$this->irp5Service->getTaxYear(),
                'deadline' => Carbon::create($now->year, 5, 31)->format('Y-m-d'),
                'type' => 'irp5',
            ],
        ];
    }

    /**
     * Authorize access to a submission
     */
    private function authorizeSubmission(ComplianceSubmission $submission): void
    {
        $user = Auth::user();
        $userBusinessIds = $user->businesses()->pluck('businesses.id');

        if (! $userBusinessIds->contains($submission->business_id)) {
            abort(403, 'Unauthorized access to this submission.');
        }
    }
}
