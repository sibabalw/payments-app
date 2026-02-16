<?php

use App\Models\Business;
use App\Models\ComplianceSubmission;
use App\Models\Employee;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\EMP201Service;
use App\Services\IRP5Service;
use App\Services\UIFDeclarationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
        'tax_id' => '7000123456',
        'registration_number' => '2024/123456/07',
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);
    $this->user->update(['current_business_id' => $this->business->id]);
});

describe('Compliance Dashboard', function () {
    it('shows compliance dashboard for authenticated user', function () {
        $response = $this->actingAs($this->user)->get('/compliance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('compliance/index')
            ->has('business')
            ->has('submissions')
            ->has('pendingItems')
            ->has('deadlines')
        );
    });

    it('displays business information', function () {
        $response = $this->actingAs($this->user)->get('/compliance');

        $response->assertInertia(fn ($page) => $page
            ->where('business.name', $this->business->name)
            ->where('business.tax_id', '7000123456')
        );
    });

    it('shows recent submissions', function () {
        ComplianceSubmission::factory()
            ->count(3)
            ->for($this->business)
            ->emp201()
            ->create();

        $response = $this->actingAs($this->user)->get('/compliance');

        $response->assertInertia(fn ($page) => $page
            ->has('submissions', 3)
        );
    });

    it('redirects unauthenticated users', function () {
        $response = $this->get('/compliance');

        $response->assertRedirect('/login');
    });
});

describe('UIF Declarations (UI-19)', function () {
    it('shows UIF declarations page', function () {
        $response = $this->actingAs($this->user)->get('/compliance/uif');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('compliance/uif/index')
            ->has('business')
            ->has('submissions')
            ->has('pendingPeriods')
        );
    });

    it('generates UI-19 declaration', function () {
        // Create employee and payroll data
        $employee = Employee::factory()->for($this->business)->create();
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        PayrollJob::factory()
            ->for($schedule)
            ->for($employee)
            ->forMonth(2026, 1)
            ->create([
                'gross_salary' => 30000,
                'uif_amount' => 177.12,
            ]);

        $response = $this->actingAs($this->user)->post('/compliance/uif/generate', [
            'period' => '2026-01',
        ]);

        $response->assertRedirect('/compliance/uif');

        $this->assertDatabaseHas('compliance_submissions', [
            'business_id' => $this->business->id,
            'type' => 'ui19',
            'period' => '2026-01',
            'status' => 'generated',
        ]);
    });

    it('downloads UI-19 CSV', function () {
        $submission = ComplianceSubmission::factory()
            ->for($this->business)
            ->ui19()
            ->create();

        $response = $this->actingAs($this->user)->get("/compliance/uif/{$submission->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('requires valid period format', function () {
        $response = $this->actingAs($this->user)->post('/compliance/uif/generate', [
            'period' => 'invalid',
        ]);

        $response->assertSessionHasErrors('period');
    });
});

describe('EMP201 Submissions', function () {
    it('shows EMP201 page', function () {
        $response = $this->actingAs($this->user)->get('/compliance/emp201');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('compliance/emp201/index')
            ->has('business')
            ->has('submissions')
        );
    });

    it('generates EMP201 data', function () {
        $employee = Employee::factory()->for($this->business)->create();
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        PayrollJob::factory()
            ->for($schedule)
            ->for($employee)
            ->forMonth(2026, 1)
            ->create([
                'gross_salary' => 50000,
                'paye_amount' => 8500,
                'uif_amount' => 177.12,
                'sdl_amount' => 500,
            ]);

        $response = $this->actingAs($this->user)->post('/compliance/emp201/generate', [
            'period' => '2026-01',
        ]);

        $response->assertRedirect('/compliance/emp201');

        $this->assertDatabaseHas('compliance_submissions', [
            'business_id' => $this->business->id,
            'type' => 'emp201',
            'period' => '2026-01',
        ]);
    });

    it('downloads EMP201 CSV', function () {
        $submission = ComplianceSubmission::factory()
            ->for($this->business)
            ->emp201()
            ->create();

        $response = $this->actingAs($this->user)->get("/compliance/emp201/{$submission->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });
});

describe('IRP5 Certificates', function () {
    it('shows IRP5 page', function () {
        $response = $this->actingAs($this->user)->get('/compliance/irp5');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('compliance/irp5/index')
            ->has('business')
            ->has('employees')
            ->has('taxYears')
        );
    });

    it('generates IRP5 for employee', function () {
        $employee = Employee::factory()->for($this->business)->create();
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        PayrollJob::factory()
            ->for($schedule)
            ->for($employee)
            ->forMonth(2025, 6)
            ->create();

        $response = $this->actingAs($this->user)->post("/compliance/irp5/generate/{$employee->id}", [
            'tax_year' => '2025/2026',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('compliance_submissions', [
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'type' => 'irp5',
            'period' => '2025/2026',
        ]);
    });

    it('generates bulk IRP5 certificates', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create();

        // Create multiple employees with payroll
        for ($i = 0; $i < 3; $i++) {
            $employee = Employee::factory()->for($this->business)->create();
            PayrollJob::factory()
                ->for($schedule)
                ->for($employee)
                ->forMonth(2025, 6)
                ->create();
        }

        $response = $this->actingAs($this->user)->post('/compliance/irp5/generate-bulk', [
            'tax_year' => '2025/2026',
        ]);

        $response->assertRedirect();
    });
});

describe('SARS Export', function () {
    it('shows SARS export page', function () {
        $response = $this->actingAs($this->user)->get('/compliance/sars-export');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('compliance/sars-export')
            ->has('business')
            ->has('submissions')
        );
    });

    it('can mark submission as submitted', function () {
        $submission = ComplianceSubmission::factory()
            ->for($this->business)
            ->emp201()
            ->create(['status' => 'generated']);

        $response = $this->actingAs($this->user)->post("/compliance/{$submission->id}/mark-submitted");

        $response->assertRedirect();

        $submission->refresh();
        expect($submission->status)->toBe('submitted');
        expect($submission->submitted_at)->not->toBeNull();
    });
});

describe('UIFDeclarationService', function () {
    it('generates monthly UI-19 data', function () {
        $employee = Employee::factory()->for($this->business)->create([
            'id_number' => '8501015009087',
        ]);
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        PayrollJob::factory()
            ->for($schedule)
            ->for($employee)
            ->forMonth(2026, 1)
            ->create([
                'gross_salary' => 30000,
                'uif_amount' => 177.12,
            ]);

        $service = app(UIFDeclarationService::class);
        $data = $service->generateMonthlyUI19($this->business, '2026-01');

        expect($data)->toHaveKey('business');
        expect($data)->toHaveKey('employees');
        expect($data)->toHaveKey('totals');
        expect($data['totals']['total_employees'])->toBe(1);
    });

    it('generates UI-19 CSV content', function () {
        $service = app(UIFDeclarationService::class);
        $data = [
            'business' => ['name' => 'Test', 'uif_reference' => 'U123'],
            'period_display' => 'January 2026',
            'employees' => [
                [
                    'id_number' => '8501015009087',
                    'employee_name' => 'John Doe',
                    'gross_remuneration' => 30000,
                    'uif_employee' => 177.12,
                    'uif_employer' => 177.12,
                    'total_uif' => 354.24,
                ],
            ],
            'totals' => [
                'total_employees' => 1,
                'total_gross_remuneration' => 30000,
                'total_uif_employee' => 177.12,
                'total_uif_employer' => 177.12,
                'total_uif_contribution' => 354.24,
            ],
        ];

        $csv = $service->generateUI19Csv($data);

        expect($csv)->toContain('UIF UI-19 Monthly Declaration');
        expect($csv)->toContain('John Doe');
    });
});

describe('EMP201Service', function () {
    it('generates EMP201 data with correct totals', function () {
        $employee = Employee::factory()->for($this->business)->create();
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        PayrollJob::factory()
            ->for($schedule)
            ->for($employee)
            ->forMonth(2026, 1)
            ->create([
                'gross_salary' => 50000,
                'paye_amount' => 8500,
                'uif_amount' => 177.12,
                'sdl_amount' => 500,
            ]);

        $service = app(EMP201Service::class);
        $data = $service->generateEMP201($this->business, '2026-01');

        expect($data)->toHaveKey('totals');
        expect($data['totals']['total_paye'])->toBe(8500.0);
        expect($data['totals']['total_uif_employee'])->toBe(177.12);
        expect($data['totals']['total_sdl'])->toBe(500.0);
    });

    it('generates EMP201 CSV content', function () {
        $service = app(EMP201Service::class);
        $data = [
            'business' => [
                'name' => 'Test Business',
                'registration_number' => '2024/123456/07',
                'paye_reference' => '7000123456',
                'sdl_reference' => '7000123456',
                'uif_reference' => 'U123456789',
            ],
            'period_display' => 'January 2026',
            'submission_deadline' => '2026-02-07',
            'employees' => [],
            'totals' => [
                'employees_count' => 1,
                'total_gross' => 50000,
                'total_paye' => 8500,
                'total_uif_employee' => 177.12,
                'total_uif_employer' => 177.12,
                'total_uif' => 354.24,
                'total_sdl' => 500,
                'total_liability' => 9354.24,
            ],
        ];

        $csv = $service->generateEMP201Csv($data);

        expect($csv)->toContain('EMP201 Monthly Employer Declaration');
        expect($csv)->toContain('PAYE Reference');
    });
});

describe('IRP5Service', function () {
    it('calculates correct tax year', function () {
        $service = app(IRP5Service::class);

        // March 2025 should be in 2025/2026 tax year
        $taxYear = $service->getTaxYear(\Carbon\Carbon::create(2025, 3, 15));
        expect($taxYear)->toBe('2025/2026');

        // February 2025 should be in 2024/2025 tax year
        $taxYear = $service->getTaxYear(\Carbon\Carbon::create(2025, 2, 15));
        expect($taxYear)->toBe('2024/2025');
    });

    it('generates IRP5 certificate data', function () {
        $employee = Employee::factory()->for($this->business)->create([
            'name' => 'Test Employee',
            'id_number' => '8501015009087',
            'tax_number' => '123456789',
        ]);
        $schedule = PayrollSchedule::factory()->for($this->business)->create();

        // Create payroll for multiple months in the tax year
        for ($month = 3; $month <= 6; $month++) {
            PayrollJob::factory()
                ->for($schedule)
                ->for($employee)
                ->forMonth(2025, $month)
                ->create([
                    'gross_salary' => 50000,
                    'paye_amount' => 8500,
                    'uif_amount' => 177.12,
                ]);
        }

        $service = app(IRP5Service::class);
        $data = $service->generateIRP5($employee, '2025/2026');

        expect($data)->toHaveKey('certificate_number');
        expect($data)->toHaveKey('employee');
        expect($data)->toHaveKey('employer');
        expect($data)->toHaveKey('income');
        expect($data)->toHaveKey('deductions');
        expect($data['employee']['name'])->toBe('Test Employee');
        expect($data['income']['total'])->toBe(200000.0); // 4 months * 50000
    });

    it('returns error when no payroll data exists', function () {
        $employee = Employee::factory()->for($this->business)->create();

        $service = app(IRP5Service::class);
        $data = $service->generateIRP5($employee, '2025/2026');

        expect($data)->toHaveKey('error');
    });
});

describe('ComplianceSubmission Model', function () {
    it('belongs to business', function () {
        $submission = ComplianceSubmission::factory()
            ->for($this->business)
            ->create();

        expect($submission->business)->toBeInstanceOf(Business::class);
        expect($submission->business->id)->toBe($this->business->id);
    });

    it('can be marked as generated', function () {
        $submission = ComplianceSubmission::factory()
            ->for($this->business)
            ->draft()
            ->create();

        $submission->markAsGenerated('/path/to/file.csv');

        expect($submission->status)->toBe('generated');
        expect($submission->file_path)->toBe('/path/to/file.csv');
    });

    it('can be marked as submitted', function () {
        $submission = ComplianceSubmission::factory()
            ->for($this->business)
            ->create(['status' => 'generated']);

        $submission->markAsSubmitted();

        expect($submission->status)->toBe('submitted');
        expect($submission->submitted_at)->not->toBeNull();
    });

    it('has scopes for filtering', function () {
        ComplianceSubmission::factory()
            ->for($this->business)
            ->ui19()
            ->create(['status' => 'generated']);

        ComplianceSubmission::factory()
            ->for($this->business)
            ->emp201()
            ->submitted()
            ->create();

        expect(ComplianceSubmission::ofType('ui19')->count())->toBe(1);
        expect(ComplianceSubmission::generated()->count())->toBe(1);
        expect(ComplianceSubmission::submitted()->count())->toBe(1);
    });
});

describe('Authorization', function () {
    it('prevents access to other business submissions', function () {
        $otherBusiness = Business::factory()->create();
        $submission = ComplianceSubmission::factory()
            ->for($otherBusiness)
            ->create();

        $response = $this->actingAs($this->user)->get("/compliance/uif/{$submission->id}/download");

        $response->assertForbidden();
    });
});
