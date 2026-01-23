<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessTemplate;
use App\Models\TemplateBlock;
use Illuminate\Support\Facades\DB;

class TemplateService
{
    /**
     * Get all template types with their current status for a business.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTemplatesForBusiness(Business $business): array
    {
        $templates = [];
        $existingTemplates = $business->templates()->get()->keyBy('type');

        foreach (BusinessTemplate::getTemplateTypes() as $type => $name) {
            $existing = $existingTemplates->get($type);

            $templates[$type] = [
                'type' => $type,
                'name' => $name,
                'preset' => $existing?->preset ?? BusinessTemplate::PRESET_DEFAULT,
                'is_customized' => $existing !== null,
                'is_active' => $existing?->is_active ?? true,
                'template_id' => $existing?->id,
            ];
        }

        return $templates;
    }

    /**
     * Get a specific template for a business, or return the default.
     */
    public function getBusinessTemplate(int $businessId, string $type): ?BusinessTemplate
    {
        return BusinessTemplate::where('business_id', $businessId)
            ->where('type', $type)
            ->where('is_active', true)
            ->with(['blocks.properties', 'blocks.tableRows'])
            ->first();
    }

    /**
     * Get the default content structure for a template type and preset.
     *
     * @return array<string, mixed>
     */
    public function getDefaultContent(string $type, string $preset = BusinessTemplate::PRESET_DEFAULT): array
    {
        $presetStyles = $this->getPresetStyles($preset);

        return match ($type) {
            BusinessTemplate::TYPE_EMAIL_PAYMENT_SUCCESS => $this->getPaymentSuccessContent($presetStyles),
            BusinessTemplate::TYPE_EMAIL_PAYMENT_FAILED => $this->getPaymentFailedContent($presetStyles),
            BusinessTemplate::TYPE_EMAIL_PAYMENT_REMINDER => $this->getPaymentReminderContent($presetStyles),
            BusinessTemplate::TYPE_EMAIL_PAYROLL_SUCCESS => $this->getPayrollSuccessContent($presetStyles),
            BusinessTemplate::TYPE_EMAIL_PAYROLL_FAILED => $this->getPayrollFailedContent($presetStyles),
            BusinessTemplate::TYPE_EMAIL_PAYSLIP => $this->getPayslipEmailContent($presetStyles),
            BusinessTemplate::TYPE_EMAIL_BUSINESS_CREATED => $this->getBusinessCreatedContent($presetStyles),
            BusinessTemplate::TYPE_PAYSLIP_PDF => $this->getPayslipPdfContent($presetStyles),
            default => $this->getGenericContent($presetStyles),
        };
    }

    /**
     * Get content array from a template (for frontend).
     *
     * @return array<string, mixed>
     */
    public function getTemplateContent(BusinessTemplate $template): array
    {
        return $template->getContentArray();
    }

    /**
     * Get preset-specific styles.
     *
     * @return array<string, mixed>
     */
    private function getPresetStyles(string $preset): array
    {
        return match ($preset) {
            BusinessTemplate::PRESET_MODERN => [
                'headerBg' => '#0f172a',
                'headerTextColor' => '#ffffff',
                'bodyBg' => '#ffffff',
                'textColor' => '#334155',
                'mutedColor' => '#64748b',
                'primaryColor' => '#3b82f6',
                'successColor' => '#22c55e',
                'errorColor' => '#ef4444',
                'borderRadius' => '12px',
                'padding' => '32px',
                'fontFamily' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            ],
            BusinessTemplate::PRESET_MINIMAL => [
                'headerBg' => '#fafafa',
                'headerTextColor' => '#171717',
                'bodyBg' => '#ffffff',
                'textColor' => '#404040',
                'mutedColor' => '#737373',
                'primaryColor' => '#171717',
                'successColor' => '#16a34a',
                'errorColor' => '#dc2626',
                'borderRadius' => '4px',
                'padding' => '24px',
                'fontFamily' => "'Georgia', serif",
            ],
            default => [ // Default preset
                'headerBg' => '#1a1a1a',
                'headerTextColor' => '#ffffff',
                'bodyBg' => '#ffffff',
                'textColor' => '#4a4a4a',
                'mutedColor' => '#6b7280',
                'primaryColor' => '#2563eb',
                'successColor' => '#22c55e',
                'errorColor' => '#ef4444',
                'borderRadius' => '8px',
                'padding' => '40px',
                'fontFamily' => "'Instrument Sans', -apple-system, BlinkMacSystemFont, sans-serif",
            ],
        };
    }

    /**
     * Get payment success email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPaymentSuccessContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Payment Successful!</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Your payment has been processed successfully.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Amount', 'value' => '{{amount}} {{currency}}'],
                            ['label' => 'Receiver', 'value' => '{{receiver_name}}'],
                            ['label' => 'Schedule', 'value' => '{{schedule_name}}'],
                            ['label' => 'Transaction ID', 'value' => '{{transaction_id}}'],
                            ['label' => 'Processed At', 'value' => '{{processed_at}}'],
                        ],
                        'headerStyle' => 'success',
                        'backgroundColor' => '#f0fdf4',
                        'borderColor' => $styles['successColor'],
                    ],
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'properties' => [
                        'label' => 'View Payment Details',
                        'url' => '{{payment_url}}',
                        'backgroundColor' => $styles['successColor'],
                        'textColor' => '#ffffff',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} {{business_name}}. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payment failed email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPaymentFailedContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Payment Failed</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['errorColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Unfortunately, your payment could not be processed.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Amount', 'value' => '{{amount}} {{currency}}'],
                            ['label' => 'Receiver', 'value' => '{{receiver_name}}'],
                            ['label' => 'Error', 'value' => '{{error_message}}'],
                        ],
                        'headerStyle' => 'error',
                        'backgroundColor' => '#fef2f2',
                        'borderColor' => $styles['errorColor'],
                    ],
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'properties' => [
                        'label' => 'Retry Payment',
                        'url' => '{{retry_url}}',
                        'backgroundColor' => $styles['primaryColor'],
                        'textColor' => '#ffffff',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} {{business_name}}. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payment reminder email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPaymentReminderContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Payment Reminder</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>This is a reminder that your scheduled payment is coming up.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Schedule', 'value' => '{{schedule_name}}'],
                            ['label' => 'Next Payment', 'value' => '{{next_payment_date}}'],
                            ['label' => 'Amount', 'value' => '{{amount}} {{currency}}'],
                        ],
                        'headerStyle' => 'info',
                        'backgroundColor' => '#eff6ff',
                        'borderColor' => $styles['primaryColor'],
                    ],
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'properties' => [
                        'label' => 'View Schedule',
                        'url' => '{{schedule_url}}',
                        'backgroundColor' => $styles['primaryColor'],
                        'textColor' => '#ffffff',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} {{business_name}}. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payroll success email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPayrollSuccessContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Payroll Processed Successfully!</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Your payroll has been processed and employees have been paid.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Total Amount', 'value' => '{{total_amount}} {{currency}}'],
                            ['label' => 'Employees Paid', 'value' => '{{employees_count}}'],
                            ['label' => 'Pay Period', 'value' => '{{pay_period}}'],
                            ['label' => 'Processed At', 'value' => '{{processed_at}}'],
                        ],
                        'headerStyle' => 'success',
                        'backgroundColor' => '#f0fdf4',
                        'borderColor' => $styles['successColor'],
                    ],
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'properties' => [
                        'label' => 'View Payroll Details',
                        'url' => '{{payroll_url}}',
                        'backgroundColor' => $styles['successColor'],
                        'textColor' => '#ffffff',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} {{business_name}}. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payroll failed email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPayrollFailedContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Payroll Processing Failed</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['errorColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Unfortunately, your payroll could not be processed.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Schedule', 'value' => '{{schedule_name}}'],
                            ['label' => 'Error', 'value' => '{{error_message}}'],
                        ],
                        'headerStyle' => 'error',
                        'backgroundColor' => '#fef2f2',
                        'borderColor' => $styles['errorColor'],
                    ],
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'properties' => [
                        'label' => 'Review Payroll',
                        'url' => '{{payroll_url}}',
                        'backgroundColor' => $styles['primaryColor'],
                        'textColor' => '#ffffff',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} {{business_name}}. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payslip email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPayslipEmailContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Your Payslip</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Hello {{employee_name}},</p><p>Your payslip for {{pay_period}} is attached to this email.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Pay Period', 'value' => '{{pay_period}}'],
                            ['label' => 'Gross Salary', 'value' => '{{currency}} {{gross_salary}}'],
                            ['label' => 'Net Salary', 'value' => '{{currency}} {{net_salary}}'],
                            ['label' => 'Payment Date', 'value' => '{{payment_date}}'],
                        ],
                        'headerStyle' => 'default',
                        'backgroundColor' => '#f5f5f5',
                        'borderColor' => '#e5e5e5',
                    ],
                ],
                [
                    'id' => 'text-3',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Please find your detailed payslip attached as a PDF. If you have any questions, please contact your employer.</p>',
                        'fontSize' => '14px',
                        'color' => $styles['mutedColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => 'Regards,<br><strong>{{business_name}}</strong>',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get business created email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getBusinessCreatedContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{app_logo}}',
                        'businessName' => 'Swift Pay',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>Welcome to Swift Pay!</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>Hello {{user_name}},</p><p>Your business <strong>{{business_name}}</strong> has been successfully created.</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'properties' => [
                        'label' => 'Go to Dashboard',
                        'url' => '{{dashboard_url}}',
                        'backgroundColor' => $styles['primaryColor'],
                        'textColor' => '#ffffff',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} Swift Pay. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payslip PDF content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getPayslipPdfContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>PAYSLIP</h1>',
                        'fontSize' => '24px',
                        'color' => $styles['textColor'],
                        'alignment' => 'center',
                    ],
                ],
                [
                    'id' => 'divider-1',
                    'type' => 'divider',
                    'properties' => [
                        'height' => '2px',
                        'color' => $styles['primaryColor'],
                    ],
                ],
                [
                    'id' => 'table-1',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Employee', 'value' => '{{employee_name}}'],
                            ['label' => 'Employee ID', 'value' => '{{employee_id}}'],
                            ['label' => 'Pay Period', 'value' => '{{pay_period}}'],
                            ['label' => 'Payment Date', 'value' => '{{payment_date}}'],
                        ],
                        'headerStyle' => 'default',
                        'backgroundColor' => '#f5f5f5',
                        'borderColor' => '#e5e5e5',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<strong>Earnings</strong>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-2',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Basic Salary', 'value' => '{{basic_salary}}'],
                            ['label' => 'Allowances', 'value' => '{{allowances}}'],
                            ['label' => 'Overtime', 'value' => '{{overtime}}'],
                            ['label' => 'Gross Salary', 'value' => '{{gross_salary}}'],
                        ],
                        'headerStyle' => 'default',
                        'backgroundColor' => '#ffffff',
                        'borderColor' => '#e5e5e5',
                    ],
                ],
                [
                    'id' => 'text-3',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<strong>Deductions</strong>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'table-3',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Tax (PAYE)', 'value' => '{{tax}}'],
                            ['label' => 'UIF', 'value' => '{{uif}}'],
                            ['label' => 'Other Deductions', 'value' => '{{other_deductions}}'],
                            ['label' => 'Total Deductions', 'value' => '{{total_deductions}}'],
                        ],
                        'headerStyle' => 'default',
                        'backgroundColor' => '#ffffff',
                        'borderColor' => '#e5e5e5',
                    ],
                ],
                [
                    'id' => 'divider-2',
                    'type' => 'divider',
                    'properties' => [
                        'height' => '2px',
                        'color' => $styles['primaryColor'],
                    ],
                ],
                [
                    'id' => 'table-4',
                    'type' => 'table',
                    'properties' => [
                        'rows' => [
                            ['label' => 'Net Salary', 'value' => '{{net_salary}}'],
                        ],
                        'headerStyle' => 'success',
                        'backgroundColor' => '#f0fdf4',
                        'borderColor' => $styles['successColor'],
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => 'This is a computer-generated document. No signature required.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get generic email content.
     *
     * @param  array<string, mixed>  $styles
     * @return array<string, mixed>
     */
    private function getGenericContent(array $styles): array
    {
        return [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'logoUrl' => '{{business_logo}}',
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => $styles['headerBg'],
                        'textColor' => $styles['headerTextColor'],
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<h1>{{title}}</h1>',
                        'fontSize' => '28px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'text-2',
                    'type' => 'text',
                    'properties' => [
                        'content' => '<p>{{content}}</p>',
                        'fontSize' => '16px',
                        'color' => $styles['textColor'],
                        'alignment' => 'left',
                    ],
                ],
                [
                    'id' => 'footer-1',
                    'type' => 'footer',
                    'properties' => [
                        'text' => '© {{year}} {{business_name}}. All rights reserved.',
                        'color' => $styles['mutedColor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Compile template blocks to HTML.
     *
     * @param  array<string, mixed>  $content
     */
    public function compileTemplate(array $content): string
    {
        $blocks = $content['blocks'] ?? [];
        $html = $this->getEmailWrapper('start');

        foreach ($blocks as $block) {
            $html .= $this->compileBlock($block);
        }

        $html .= $this->getEmailWrapper('end');

        return $html;
    }

    /**
     * Get email wrapper HTML.
     */
    private function getEmailWrapper(string $part): string
    {
        if ($part === 'start') {
            return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{subject}}</title>
    <style>
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            background-color: #f5f5f5;
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #1a1a1a;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f5f5f5;
            padding: 20px 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-content {
            padding: 40px 30px;
        }
        h1 { font-size: 28px; font-weight: 600; line-height: 1.3; margin: 0 0 20px 0; color: #1a1a1a; }
        h2 { font-size: 24px; font-weight: 600; line-height: 1.3; margin: 0 0 16px 0; color: #1a1a1a; }
        p { margin: 0 0 16px 0; color: #4a4a4a; }
        @media only screen and (max-width: 600px) {
            .email-content { padding: 30px 20px; }
            h1 { font-size: 24px; }
            h2 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; width: 100%;">
HTML;
        }

        return <<<'HTML'
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Compile a single block to HTML.
     *
     * @param  array<string, mixed>  $block
     */
    private function compileBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        $props = $block['properties'] ?? [];

        return match ($type) {
            'header' => $this->compileHeaderBlock($props),
            'text' => $this->compileTextBlock($props),
            'button' => $this->compileButtonBlock($props),
            'divider' => $this->compileDividerBlock($props),
            'table' => $this->compileTableBlock($props),
            'footer' => $this->compileFooterBlock($props),
            default => '',
        };
    }

    /**
     * Compile header block.
     *
     * @param  array<string, mixed>  $props
     */
    private function compileHeaderBlock(array $props): string
    {
        $bgColor = $props['backgroundColor'] ?? '#1a1a1a';
        $textColor = $props['textColor'] ?? '#ffffff';
        $businessName = $props['businessName'] ?? '';
        $logoUrl = $props['logoUrl'] ?? '';

        $logoHtml = $logoUrl
            ? "<img src=\"{$logoUrl}\" alt=\"{$businessName}\" style=\"height: 40px; width: auto;\">"
            : '';

        return <<<HTML
<tr>
    <td style="background-color: {$bgColor}; padding: 24px 30px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="color: {$textColor}; font-size: 20px; font-weight: 600;">
                    {$logoHtml}
                    {$businessName}
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    /**
     * Compile text block.
     *
     * @param  array<string, mixed>  $props
     */
    private function compileTextBlock(array $props): string
    {
        $content = $props['content'] ?? '';
        $fontSize = $props['fontSize'] ?? '16px';
        $color = $props['color'] ?? '#4a4a4a';
        $alignment = $props['alignment'] ?? 'left';

        return <<<HTML
<tr>
    <td style="padding: 16px 30px; font-size: {$fontSize}; color: {$color}; text-align: {$alignment};">
        {$content}
    </td>
</tr>
HTML;
    }

    /**
     * Compile button block.
     *
     * @param  array<string, mixed>  $props
     */
    private function compileButtonBlock(array $props): string
    {
        $label = $props['label'] ?? 'Click Here';
        $url = $props['url'] ?? '#';
        $bgColor = $props['backgroundColor'] ?? '#2563eb';
        $textColor = $props['textColor'] ?? '#ffffff';

        return <<<HTML
<tr>
    <td style="padding: 24px 30px;" align="center">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td align="center" style="background-color: {$bgColor}; border-radius: 8px; padding: 0;">
                    <a href="{$url}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: {$textColor}; text-decoration: none; border-radius: 8px;">
                        {$label}
                    </a>
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    /**
     * Compile divider block.
     *
     * @param  array<string, mixed>  $props
     */
    private function compileDividerBlock(array $props): string
    {
        $height = $props['height'] ?? '1px';
        $color = $props['color'] ?? '#e5e5e5';

        return <<<HTML
<tr>
    <td style="padding: 16px 30px;">
        <div style="height: {$height}; background-color: {$color};"></div>
    </td>
</tr>
HTML;
    }

    /**
     * Compile table block.
     *
     * @param  array<string, mixed>  $props
     */
    private function compileTableBlock(array $props): string
    {
        $rows = $props['rows'] ?? [];
        $bgColor = $props['backgroundColor'] ?? '#f5f5f5';
        $borderColor = $props['borderColor'] ?? '#e5e5e5';

        $rowsHtml = '';
        foreach ($rows as $row) {
            $label = $row['label'] ?? '';
            $value = $row['value'] ?? '';
            $rowsHtml .= <<<HTML
<tr>
    <td style="padding: 12px; border-bottom: 1px solid {$borderColor};"><strong>{$label}:</strong></td>
    <td style="padding: 12px; border-bottom: 1px solid {$borderColor};">{$value}</td>
</tr>
HTML;
        }

        return <<<HTML
<tr>
    <td style="padding: 16px 30px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: {$bgColor}; border-left: 4px solid {$borderColor}; border-radius: 4px;">
            {$rowsHtml}
        </table>
    </td>
</tr>
HTML;
    }

    /**
     * Compile footer block.
     *
     * @param  array<string, mixed>  $props
     */
    private function compileFooterBlock(array $props): string
    {
        $text = $props['text'] ?? '';
        $color = $props['color'] ?? '#6b7280';

        return <<<HTML
<tr>
    <td style="background-color: #f5f5f5; padding: 24px 30px; text-align: center; font-size: 14px; color: {$color};">
        {$text}
    </td>
</tr>
HTML;
    }

    /**
     * Render a template with data.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderTemplate(string $html, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $replacement = (string) $value;

                // Convert relative logo paths to base64 data URIs for email embedding
                if ($key === 'business_logo' && $replacement && ! str_starts_with($replacement, 'data:')) {
                    \Illuminate\Support\Facades\Log::info('TemplateService: Processing business_logo', [
                        'original_value' => $replacement,
                        'is_url' => filter_var($replacement, FILTER_VALIDATE_URL),
                    ]);

                    // If it's not already a data URI, try to convert it
                    if (! filter_var($replacement, FILTER_VALIDATE_URL)) {
                        // It's a relative path, convert to base64
                        try {
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($replacement)) {
                                $logoContents = \Illuminate\Support\Facades\Storage::disk('public')->get($replacement);
                                $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($replacement) ?: 'image/png';
                                $base64 = base64_encode($logoContents);
                                $replacement = "data:{$mimeType};base64,{$base64}";

                                \Illuminate\Support\Facades\Log::info('TemplateService: Logo converted to base64', [
                                    'mime_type' => $mimeType,
                                    'base64_length' => strlen($base64),
                                ]);
                            } else {
                                \Illuminate\Support\Facades\Log::warning('TemplateService: Logo file not found', [
                                    'logo_path' => $replacement,
                                ]);
                                $replacement = '';
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('TemplateService: Failed to convert logo', [
                                'logo_path' => $replacement,
                                'error' => $e->getMessage(),
                            ]);
                            $replacement = '';
                        }
                    }
                }

                $html = str_replace("{{{$key}}}", $replacement, $html);
            }
        }

        return $html;
    }

    /**
     * Save or update a template for a business.
     *
     * @param  array<string, mixed>  $content
     */
    public function saveTemplate(
        Business $business,
        string $type,
        string $name,
        array $content,
        string $preset = BusinessTemplate::PRESET_DEFAULT
    ): BusinessTemplate {
        return DB::transaction(function () use ($business, $type, $name, $content, $preset) {
            // Create or update the template
            $template = BusinessTemplate::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'type' => $type,
                ],
                [
                    'name' => $name,
                    'preset' => $preset,
                    'is_active' => true,
                ]
            );

            // Delete existing blocks (cascade will delete properties and table rows)
            $template->blocks()->delete();

            // Create new blocks from content
            $blocks = $content['blocks'] ?? [];
            foreach ($blocks as $index => $blockData) {
                $block = $template->blocks()->create([
                    'block_id' => $blockData['id'] ?? 'block-'.$index,
                    'type' => $blockData['type'],
                    'sort_order' => $index,
                ]);

                $properties = $blockData['properties'] ?? [];

                // Handle table rows separately
                if ($blockData['type'] === TemplateBlock::TYPE_TABLE && isset($properties['rows'])) {
                    $rows = $properties['rows'];
                    unset($properties['rows']);

                    foreach ($rows as $rowIndex => $row) {
                        $block->tableRows()->create([
                            'label' => $row['label'] ?? '',
                            'value' => $row['value'] ?? '',
                            'sort_order' => $rowIndex,
                        ]);
                    }
                }

                // Save other properties
                foreach ($properties as $key => $value) {
                    $block->properties()->create([
                        'key' => $key,
                        'value' => is_string($value) ? $value : json_encode($value),
                    ]);
                }
            }

            // Compile and save HTML
            $template->compiled_html = $this->compileTemplate($content);
            $template->save();

            return $template;
        });
    }

    /**
     * Reset a template to default.
     */
    public function resetTemplate(Business $business, string $type): bool
    {
        return DB::transaction(function () use ($business, $type) {
            // Deleting the template will cascade delete blocks, properties, and table rows
            return BusinessTemplate::where('business_id', $business->id)
                ->where('type', $type)
                ->delete() > 0;
        });
    }
}
