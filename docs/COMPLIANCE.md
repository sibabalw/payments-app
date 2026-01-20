# South African Tax Compliance Documentation

This document details the South African tax compliance features implemented in Swift Pay.

## Overview

Swift Pay handles the following South African tax compliance requirements:

1. **PAYE** (Pay As You Earn) - Income tax
2. **UIF** (Unemployment Insurance Fund) - Employee and employer contributions
3. **SDL** (Skills Development Levy) - Training levy
4. **UI-19** - Monthly UIF declarations
5. **EMP201** - Monthly SARS reconciliation
6. **IRP5** - Annual tax certificates

## Tax Calculations

### PAYE (Pay As You Earn)

PAYE is calculated using the SARS tax brackets for the current tax year.

**Service**: `App\Services\SouthAfricanTaxService`

```php
// Example: Calculate monthly PAYE
$taxService = app(SouthAfricanTaxService::class);
$paye = $taxService->calculatePAYE($annualTaxableIncome, $age);
```

**2025/2026 Tax Brackets**:

| Taxable Income (Annual) | Rate |
|-------------------------|------|
| R0 - R237,100 | 18% |
| R237,101 - R370,500 | 26% |
| R370,501 - R512,800 | 31% |
| R512,801 - R673,000 | 36% |
| R673,001 - R857,900 | 39% |
| R857,901 - R1,817,000 | 41% |
| R1,817,001+ | 45% |

**Tax Rebates** (2025/2026):
- Primary (all taxpayers): R17,235
- Secondary (65+): R9,444
- Tertiary (75+): R3,145

### UIF (Unemployment Insurance Fund)

Both employer and employee contribute 1% of remuneration, capped at the UIF ceiling.

**2025/2026 Cap**: R17,712 per month (R212,544 annually)

```php
// UIF Calculation
$employeeUIF = min($grossSalary * 0.01, 177.12);
$employerUIF = min($grossSalary * 0.01, 177.12);
$totalUIF = $employeeUIF + $employerUIF;
```

### SDL (Skills Development Levy)

Employers with annual payroll exceeding R500,000 pay 1% of total payroll.

```php
// SDL Calculation (employer only)
$sdl = $grossSalary * 0.01;
```

## Compliance Documents

### UI-19 Monthly Declaration

The UI-19 is a monthly UIF declaration submitted to the Department of Labour.

**Service**: `App\Services\UIFDeclarationService`

**Features**:
- Generate monthly contribution reports
- Include new employee registrations
- Include termination declarations
- Export to CSV format

**Generated Data Structure**:
```php
[
    'period' => '2026-01',
    'business' => [
        'name' => 'Company Name',
        'uif_reference' => 'UIF123456',
        // ...
    ],
    'employees' => [
        [
            'id_number' => '8501015800086',
            'name' => 'John Doe',
            'gross_remuneration' => 25000.00,
            'employee_contribution' => 177.12,
            'employer_contribution' => 177.12,
        ],
        // ...
    ],
    'totals' => [
        'gross_remuneration' => 125000.00,
        'employee_contributions' => 885.60,
        'employer_contributions' => 885.60,
        'total_contributions' => 1771.20,
    ],
]
```

**CSV Export Format**:
```csv
ID Number,Employee Name,Gross Remuneration,Employee UIF,Employer UIF
8501015800086,John Doe,25000.00,177.12,177.12
```

### EMP201 Monthly Submission

The EMP201 is a monthly employer declaration submitted to SARS.

**Service**: `App\Services\EMP201Service`

**Features**:
- Calculate total PAYE liability
- Calculate total UIF liability
- Calculate total SDL liability
- Generate submission checklist
- Export to SARS-compatible CSV

**Generated Data Structure**:
```php
[
    'period' => '2026-01',
    'business' => [
        'name' => 'Company Name',
        'paye_reference' => 'PAYE123456',
        'uif_reference' => 'UIF123456',
        'sdl_reference' => 'SDL123456',
    ],
    'summary' => [
        'total_paye' => 45000.00,
        'total_uif_employee' => 885.60,
        'total_uif_employer' => 885.60,
        'total_sdl' => 1250.00,
        'total_liability' => 48021.20,
    ],
    'employees' => [
        [
            'tax_number' => '1234567890',
            'name' => 'John Doe',
            'gross_salary' => 25000.00,
            'paye' => 3500.00,
            'uif_employee' => 177.12,
            'uif_employer' => 177.12,
            'sdl' => 250.00,
        ],
        // ...
    ],
    'checklist' => [
        'has_paye_reference' => true,
        'has_uif_reference' => true,
        'all_employees_have_tax_numbers' => true,
        // ...
    ],
]
```

### IRP5 Tax Certificates

IRP5 certificates are annual tax certificates issued to employees.

**Service**: `App\Services\IRP5Service`

**Features**:
- Generate individual certificates
- Bulk generation for all employees
- Include all income sources with SARS codes
- Include all deductions with SARS codes
- Export to PDF format

**Tax Year**: March 1 to February 28/29

**SARS Income Codes**:
| Code | Description |
|------|-------------|
| 3601 | Basic salary |
| 3605 | Annual bonus |
| 3701 | Commission |
| 3702 | Overtime payments |
| 3713 | Travel allowance |
| 3802 | Employer pension contributions |

**SARS Deduction Codes**:
| Code | Description |
|------|-------------|
| 4001 | Current pension fund contributions |
| 4002 | Arrear pension fund contributions |
| 4003 | Provident fund contributions |
| 4005 | Medical aid contributions |
| 4474 | Donations (s18A) |

**Generated Data Structure**:
```php
[
    'certificate_number' => 'IRP5-2026-001',
    'tax_year' => '2025/2026',
    'employer' => [
        'name' => 'Company Name',
        'paye_reference' => 'PAYE123456',
        'trading_name' => 'Company Trading Name',
        'address' => '123 Main St, City',
    ],
    'employee' => [
        'name' => 'John Doe',
        'id_number' => '8501015800086',
        'tax_number' => '1234567890',
        'employment_start' => '2020-01-15',
        'employment_end' => null,
    ],
    'income' => [
        ['code' => '3601', 'description' => 'Basic salary', 'amount' => 300000.00],
        ['code' => '3702', 'description' => 'Overtime', 'amount' => 15000.00],
    ],
    'deductions' => [
        ['code' => '4001', 'description' => 'Pension fund', 'amount' => 22500.00],
        ['code' => '4005', 'description' => 'Medical aid', 'amount' => 24000.00],
    ],
    'paye_deducted' => 52000.00,
]
```

## Compliance Workflow

### Monthly Workflow

1. **By the 7th**: Generate UI-19
   - Navigate to Compliance > UIF Declarations
   - Select the month
   - Generate and review
   - Download CSV
   - Submit to Department of Labour

2. **By the 7th**: Generate EMP201
   - Navigate to Compliance > EMP201
   - Select the month
   - Review tax summary
   - Complete checklist
   - Download for SARS eFiling upload

3. **Payment**: Pay taxes via SARS eFiling by the 7th

### Annual Workflow (February/March)

1. **Generate IRP5 Certificates**
   - Navigate to Compliance > IRP5
   - Select tax year
   - Generate certificates (individual or bulk)
   - Review and edit if needed
   - Download PDFs for employees

2. **Submit to SARS**
   - Export IRP5 data
   - Upload via SARS eFiling
   - Submit EMP501 reconciliation

## Editing Compliance Documents

All compliance documents can be edited before final submission:

1. Generate the document
2. Click "Edit" on the submission
3. Modify employee data, amounts, or codes
4. Save changes
5. Download the updated version

**Note**: Once a document is marked as "Submitted", it cannot be edited.

## Database Schema

### compliance_submissions Table

```sql
CREATE TABLE compliance_submissions (
    id BIGINT PRIMARY KEY,
    business_id BIGINT REFERENCES businesses(id),
    employee_id BIGINT NULLABLE REFERENCES employees(id),
    type VARCHAR(50),        -- 'ui19', 'emp201', 'irp5'
    period VARCHAR(20),      -- '2026-01' or '2025/2026'
    status VARCHAR(20),      -- 'draft', 'generated', 'submitted'
    data JSON,               -- Full submission data
    file_path VARCHAR(255),  -- Generated file location
    submitted_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## API Endpoints

### UIF Declarations

```
GET  /compliance/uif                 - List UI-19 submissions
POST /compliance/uif/generate        - Generate new UI-19
GET  /compliance/uif/{id}/edit       - Edit UI-19
PUT  /compliance/uif/{id}            - Update UI-19
GET  /compliance/uif/{id}/download   - Download CSV
```

### EMP201 Submissions

```
GET  /compliance/emp201              - List EMP201 submissions
POST /compliance/emp201/generate     - Generate new EMP201
GET  /compliance/emp201/{id}/edit    - Edit EMP201
PUT  /compliance/emp201/{id}         - Update EMP201
GET  /compliance/emp201/{id}/download - Download CSV
```

### IRP5 Certificates

```
GET  /compliance/irp5                     - List IRP5 certificates
POST /compliance/irp5/generate/{employee} - Generate for employee
POST /compliance/irp5/generate-bulk       - Generate for all employees
GET  /compliance/irp5/{id}/edit           - Edit IRP5
PUT  /compliance/irp5/{id}                - Update IRP5
GET  /compliance/irp5/{id}/download       - Download PDF
```

### SARS Export

```
GET  /compliance/sars-export              - SARS export page
POST /compliance/{id}/mark-submitted      - Mark as submitted to SARS
```

## Important Dates

| Deadline | Description |
|----------|-------------|
| 7th of month | EMP201 submission and payment |
| 7th of month | UI-19 submission |
| End of May | IRP5 certificates issued |
| End of May | EMP501 reconciliation due |

## Resources

- [SARS eFiling](https://www.sarsefiling.co.za/)
- [Department of Labour uFiling](https://www.ufiling.co.za/)
- [SARS Tax Tables](https://www.sars.gov.za/tax-rates/income-tax/)
