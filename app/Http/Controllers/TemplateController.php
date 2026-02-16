<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessTemplate;
use App\Services\TemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    /**
     * Display a listing of all template types.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            return Inertia::render('templates/index', [
                'templates' => [],
                'presets' => BusinessTemplate::getPresets(),
                'error' => 'Please select a business first.',
            ]);
        }

        $business = Business::findOrFail($businessId);
        $templates = $this->templateService->getTemplatesForBusiness($business);

        return Inertia::render('templates/index', [
            'templates' => $templates,
            'presets' => BusinessTemplate::getPresets(),
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
        ]);
    }

    /**
     * Display the template editor for a specific type.
     */
    public function show(Request $request, string $type): Response|RedirectResponse
    {
        if (! BusinessTemplate::isValidType($type)) {
            return redirect()->route('templates.index')
                ->with('error', 'Invalid template type.');
        }

        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            return redirect()->route('templates.index')
                ->with('error', 'Please select a business first.');
        }

        $business = Business::findOrFail($businessId);
        $existingTemplate = $this->templateService->getBusinessTemplate($businessId, $type);

        $preset = $existingTemplate?->preset ?? BusinessTemplate::PRESET_DEFAULT;
        $content = $existingTemplate
            ? $existingTemplate->getContentArray()
            : $this->templateService->getDefaultContent($type, $preset);

        return Inertia::render('templates/editor', [
            'type' => $type,
            'typeName' => BusinessTemplate::getTemplateTypes()[$type],
            'preset' => $preset,
            'content' => $content,
            'presets' => BusinessTemplate::getPresets(),
            'isCustomized' => $existingTemplate !== null,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'logo' => $business->logo,
            ],
        ]);
    }

    /**
     * Update a template for the current business.
     */
    public function update(Request $request, string $type): RedirectResponse
    {
        if (! BusinessTemplate::isValidType($type)) {
            return redirect()->route('templates.index')
                ->with('error', 'Invalid template type.');
        }

        $validated = $request->validate([
            'content' => ['required', 'array'],
            'content.blocks' => ['required', 'array'],
            'preset' => ['required', 'string', 'in:'.implode(',', array_keys(BusinessTemplate::getPresets()))],
        ]);

        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            return redirect()->route('templates.index')
                ->with('error', 'Please select a business first.');
        }

        $business = Business::findOrFail($businessId);
        $typeName = BusinessTemplate::getTemplateTypes()[$type];

        $this->templateService->saveTemplate(
            $business,
            $type,
            $typeName,
            $validated['content'],
            $validated['preset']
        );

        return redirect()->route('templates.show', $type)
            ->with('success', 'Template saved successfully.');
    }

    /**
     * Reset a template to default.
     */
    public function reset(Request $request, string $type): RedirectResponse
    {
        if (! BusinessTemplate::isValidType($type)) {
            return redirect()->route('templates.index')
                ->with('error', 'Invalid template type.');
        }

        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            return redirect()->route('templates.index')
                ->with('error', 'Please select a business first.');
        }

        $business = Business::findOrFail($businessId);
        $this->templateService->resetTemplate($business, $type);

        return redirect()->route('templates.show', $type)
            ->with('success', 'Template reset to default.');
    }

    /**
     * Preview a template with sample data.
     */
    public function preview(Request $request, string $type): \Illuminate\Http\Response
    {
        if (! BusinessTemplate::isValidType($type)) {
            abort(404, 'Invalid template type.');
        }

        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            abort(400, 'Please select a business first.');
        }

        $business = Business::findOrFail($businessId);

        // Get content from request or from saved template
        $content = $request->input('content');
        if (! $content) {
            $existingTemplate = $this->templateService->getBusinessTemplate($businessId, $type);
            $preset = $existingTemplate?->preset ?? BusinessTemplate::PRESET_DEFAULT;
            $content = $existingTemplate
                ? $existingTemplate->getContentArray()
                : $this->templateService->getDefaultContent($type, $preset);
        }

        $html = $this->templateService->compileTemplate($content);

        // Replace placeholders with sample data
        $sampleData = $this->getSampleData($type, $business);
        $html = $this->templateService->renderTemplate($html, $sampleData);

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Load a preset template content.
     */
    public function loadPreset(Request $request, string $type): \Illuminate\Http\JsonResponse
    {
        if (! BusinessTemplate::isValidType($type)) {
            return response()->json(['error' => 'Invalid template type.'], 400);
        }

        $preset = $request->input('preset', BusinessTemplate::PRESET_DEFAULT);

        if (! BusinessTemplate::isValidPreset($preset)) {
            return response()->json(['error' => 'Invalid preset.'], 400);
        }

        $content = $this->templateService->getDefaultContent($type, $preset);

        return response()->json([
            'content' => $content,
            'preset' => $preset,
        ]);
    }

    /**
     * Get sample data for template preview.
     *
     * @return array<string, string>
     */
    private function getSampleData(string $type, Business $business): array
    {
        $baseData = [
            'subject' => 'Email Preview',
            'business_name' => $business->name,
            'business_logo' => $business->logo ?? '',
            'app_logo' => asset('logo.svg'),
            'year' => date('Y'),
            'user_name' => 'John Doe',
            'dashboard_url' => route('dashboard'),
        ];

        return match ($type) {
            BusinessTemplate::TYPE_EMAIL_PAYMENT_SUCCESS => array_merge($baseData, [
                'amount' => '1,500.00',
                'currency' => 'ZAR',
                'receiver_name' => 'Jane Smith',
                'schedule_name' => 'Monthly Rent',
                'transaction_id' => 'TXN-2026-001234',
                'processed_at' => now()->format('F d, Y \a\t g:i A'),
                'payment_url' => route('payments.jobs'),
            ]),
            BusinessTemplate::TYPE_EMAIL_PAYMENT_FAILED => array_merge($baseData, [
                'amount' => '1,500.00',
                'currency' => 'ZAR',
                'receiver_name' => 'Jane Smith',
                'error_message' => 'Insufficient funds in escrow account.',
                'retry_url' => route('payments.index'),
            ]),
            BusinessTemplate::TYPE_EMAIL_PAYMENT_REMINDER => array_merge($baseData, [
                'schedule_name' => 'Monthly Rent',
                'next_payment_date' => now()->addDays(3)->format('F d, Y'),
                'amount' => '1,500.00',
                'currency' => 'ZAR',
                'schedule_url' => route('payments.index'),
            ]),
            BusinessTemplate::TYPE_EMAIL_PAYROLL_SUCCESS => array_merge($baseData, [
                'total_amount' => '45,000.00',
                'currency' => 'ZAR',
                'employees_count' => '12',
                'pay_period' => 'January 2026',
                'processed_at' => now()->format('F d, Y \a\t g:i A'),
                'payroll_url' => route('payroll.jobs'),
            ]),
            BusinessTemplate::TYPE_EMAIL_PAYROLL_FAILED => array_merge($baseData, [
                'schedule_name' => 'Monthly Payroll',
                'error_message' => 'Insufficient funds in escrow account.',
                'payroll_url' => route('payroll.index'),
            ]),
            BusinessTemplate::TYPE_EMAIL_PAYSLIP => array_merge($baseData, [
                'employee_name' => 'John Doe',
                'pay_period' => 'January 2026',
                'currency' => 'ZAR',
                'gross_salary' => '25,000.00',
                'net_salary' => '19,250.00',
                'payment_date' => now()->format('F d, Y'),
            ]),
            BusinessTemplate::TYPE_EMAIL_BUSINESS_CREATED => $baseData,
            BusinessTemplate::TYPE_PAYSLIP_PDF => array_merge($baseData, [
                'employee_name' => 'John Doe',
                'employee_id' => 'EMP-001',
                'pay_period' => 'January 1 - January 31, 2026',
                'payment_date' => now()->format('F d, Y'),
                'basic_salary' => 'ZAR 20,000.00',
                'allowances' => 'ZAR 3,000.00',
                'overtime' => 'ZAR 2,000.00',
                'gross_salary' => 'ZAR 25,000.00',
                'tax' => 'ZAR 4,500.00',
                'uif' => 'ZAR 250.00',
                'other_deductions' => 'ZAR 1,000.00',
                'total_deductions' => 'ZAR 5,750.00',
                'net_salary' => 'ZAR 19,250.00',
            ]),
            default => $baseData,
        };
    }
}
