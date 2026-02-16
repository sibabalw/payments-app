# API & Routes Documentation

This document details all HTTP routes and API endpoints in SwiftPay.

## Authentication

SwiftPay uses Laravel Fortify for authentication with session-based auth for web and optional API tokens.

### Authentication Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/login` | Show login form |
| POST | `/login` | Authenticate user |
| POST | `/logout` | Logout user |
| GET | `/register` | Show registration form |
| POST | `/register` | Create new account |
| GET | `/forgot-password` | Show password reset form |
| POST | `/forgot-password` | Send reset link |
| GET | `/reset-password/{token}` | Show reset form |
| POST | `/reset-password` | Reset password |
| GET | `/email/verify` | Email verification notice |
| GET | `/email/verify/{id}/{hash}` | Verify email |
| POST | `/email/verification-notification` | Resend verification |

### Two-Factor Authentication

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/two-factor-challenge` | 2FA challenge form |
| POST | `/two-factor-challenge` | Verify 2FA code |

### Google OAuth

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/auth/google` | Redirect to Google |
| GET | `/auth/google/callback` | Google callback |

## Public Routes

No authentication required.

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/` | `home` | Home page |
| GET | `/features` | `features` | Features page |
| GET | `/pricing` | `pricing` | Pricing page |
| GET | `/about` | `about` | About page |
| GET | `/contact` | `contact` | Contact page |
| GET | `/privacy` | `privacy` | Privacy policy |
| GET | `/terms` | `terms` | Terms of service |

## Employee Portal Routes

OTP-based authentication for employees.

### Public (No Auth)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/employee/sign-in` | `employee.sign-in` | Employee sign-in page |
| POST | `/employee/send-otp` | `employee.send-otp` | Send OTP to employee |
| POST | `/employee/verify-otp` | `employee.verify-otp` | Verify employee OTP |

### Protected (OTP Verified)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/employee/time-tracking` | `employee.time-tracking` | Time tracking portal |
| POST | `/employee/time-tracking/sign-in` | `employee.time-tracking.sign-in` | Sign in |
| POST | `/employee/time-tracking/sign-out` | `employee.time-tracking.sign-out` | Sign out |
| POST | `/employee/sign-out-session` | `employee.sign-out-session` | Logout from portal |

## Protected Routes

All routes below require authentication (`auth` middleware) and email verification (`verified` middleware).

### Dashboard

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/dashboard` | `dashboard` | Main dashboard |

**Query Parameters:**
- `frequency`: `weekly`, `monthly`, `quarterly`, `yearly`
- `trends_frequency`: Override for trends chart
- `successRate_frequency`: Override for success rate chart

**Response Data:**
```typescript
{
  metrics: {
    total_schedules: number,
    active_schedules: number,
    pending_jobs: number,
    processing_jobs: number,
    succeeded_jobs: number,
    failed_jobs: number
  },
  financial: {
    total_payments_this_month: number,
    total_payroll_this_month: number,
    total_fees_this_month: number,
    success_rate: number
  },
  monthlyTrends: Array<{ month: string, payments: number, payroll: number }>,
  upcomingPayments: Array<Schedule>,
  recentJobs: Array<Job>,
  businessInfo: BusinessInfo | null
}
```

### Onboarding

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/onboarding` | `onboarding.index` | Onboarding wizard |
| POST | `/onboarding` | `onboarding.store` | Complete onboarding |
| POST | `/onboarding/skip` | `onboarding.skip` | Skip onboarding |

### Businesses

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/businesses` | `businesses.index` | List businesses |
| GET | `/businesses/create` | `businesses.create` | Create form |
| POST | `/businesses` | `businesses.store` | Create business |
| GET | `/businesses/{id}/edit` | `businesses.edit` | Edit form |
| PUT | `/businesses/{id}` | `businesses.update` | Update business |
| DELETE | `/businesses/{id}` | `businesses.destroy` | Delete business |
| POST | `/businesses/{id}/switch` | `businesses.switch` | Switch active business |
| POST | `/businesses/{id}/status` | `businesses.status` | Update status (admin) |
| GET | `/businesses/{id}/bank-account` | `businesses.bank-account.edit` | Bank account form |
| PUT | `/businesses/{id}/bank-account` | `businesses.bank-account.update` | Update bank account |
| POST | `/businesses/{id}/send-email-otp` | `businesses.send-email-otp` | Send email verification OTP |
| POST | `/businesses/{id}/verify-email-otp` | `businesses.verify-email-otp` | Verify email OTP |

**Create/Update Request:**
```typescript
{
  name: string,
  business_type?: string,
  registration_number?: string,
  tax_id?: string,
  email?: string,
  phone?: string,
  website?: string,
  street_address?: string,
  city?: string,
  province?: string,
  postal_code?: string,
  description?: string,
  contact_person_name?: string
}
```

### Recipients

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/recipients` | `recipients.index` | List recipients |
| GET | `/recipients/create` | `recipients.create` | Create form |
| POST | `/recipients` | `recipients.store` | Create recipient |
| GET | `/recipients/{id}` | `recipients.show` | View recipient |
| GET | `/recipients/{id}/edit` | `recipients.edit` | Edit form |
| PUT | `/recipients/{id}` | `recipients.update` | Update recipient |
| DELETE | `/recipients/{id}` | `recipients.destroy` | Delete recipient |

**Create/Update Request:**
```typescript
{
  name: string,
  email?: string,
  phone?: string,
  bank_name?: string,
  account_number?: string,
  branch_code?: string,
  account_type?: string,
  reference?: string,
  notes?: string
}
```

### Employees

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/employees` | `employees.index` | List employees |
| GET | `/employees/create` | `employees.create` | Create form |
| POST | `/employees` | `employees.store` | Create employee |
| GET | `/employees/{id}` | `employees.show` | View employee |
| GET | `/employees/{id}/edit` | `employees.edit` | Edit form |
| PUT | `/employees/{id}` | `employees.update` | Update employee |
| DELETE | `/employees/{id}` | `employees.destroy` | Delete employee |
| POST | `/employees/calculate-tax` | `employees.calculate-tax` | Calculate tax preview |
| GET | `/employees/{id}/schedule` | `employees.schedule` | Work schedule |
| PUT | `/employees/{id}/schedule` | `employees.schedule.update` | Update schedule |
| GET | `/employees/{id}/payslips` | `employees.payslips` | Employee payslips |

**Create/Update Request:**
```typescript
{
  name: string,
  email: string,
  id_number?: string,
  tax_number?: string,
  employment_type: 'full_time' | 'part_time' | 'contractor' | 'temporary',
  department?: string,
  start_date?: string,
  gross_salary: number,
  hourly_rate?: number,
  overtime_rate_multiplier?: number,
  bank_account_details?: {
    bank_name: string,
    account_number: string,
    branch_code: string,
    account_type: string
  },
  tax_status?: string,
  notes?: string
}
```

**Tax Calculation Response:**
```typescript
{
  gross_salary: number,
  paye: number,
  uif_employee: number,
  uif_employer: number,
  sdl: number,
  net_salary: number
}
```

### Custom Deductions

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/deductions` | `deductions.index` | List deductions |
| GET | `/deductions/create` | `deductions.create` | Create form |
| POST | `/deductions` | `deductions.store` | Create deduction |
| GET | `/deductions/{id}/edit` | `deductions.edit` | Edit form |
| PUT | `/deductions/{id}` | `deductions.update` | Update deduction |
| DELETE | `/deductions/{id}` | `deductions.destroy` | Delete deduction |
| GET | `/employees/{id}/deductions` | `employees.deductions.index` | Employee deductions |

### Payment Schedules

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/payments` | `payments.index` | List schedules |
| GET | `/payments/create` | `payments.create` | Create form |
| POST | `/payments` | `payments.store` | Create schedule |
| GET | `/payments/{id}` | `payments.show` | View schedule |
| GET | `/payments/{id}/edit` | `payments.edit` | Edit form |
| PUT | `/payments/{id}` | `payments.update` | Update schedule |
| DELETE | `/payments/{id}` | `payments.destroy` | Delete schedule |
| POST | `/payments/{id}/pause` | `payments.pause` | Pause schedule |
| POST | `/payments/{id}/resume` | `payments.resume` | Resume schedule |
| POST | `/payments/{id}/cancel` | `payments.cancel` | Cancel schedule |
| GET | `/payments/jobs` | `payments.jobs` | List payment jobs |
| GET | `/payments/jobs/{id}` | `payments.jobs.show` | View job details |

**Create/Update Request:**
```typescript
{
  name: string,
  amount: number,
  currency?: string,
  frequency: 'weekly' | 'bi-weekly' | 'monthly' | 'quarterly' | 'annually',
  schedule_type: 'one_time' | 'recurring',
  next_run_at?: string,
  recipient_ids: number[]
}
```

### Payroll Schedules

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/payroll` | `payroll.index` | List schedules |
| GET | `/payroll/create` | `payroll.create` | Create form |
| POST | `/payroll` | `payroll.store` | Create schedule |
| GET | `/payroll/{id}/edit` | `payroll.edit` | Edit form |
| PUT | `/payroll/{id}` | `payroll.update` | Update schedule |
| DELETE | `/payroll/{id}` | `payroll.destroy` | Delete schedule |
| POST | `/payroll/{id}/pause` | `payroll.pause` | Pause schedule |
| POST | `/payroll/{id}/resume` | `payroll.resume` | Resume schedule |
| POST | `/payroll/{id}/cancel` | `payroll.cancel` | Cancel schedule |
| GET | `/payroll/jobs` | `payroll.jobs` | List payroll jobs |
| GET | `/payroll/jobs/{id}` | `payroll.jobs.show` | View job details |

### Payslips

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/payslips` | `payslips.index` | List payslips |
| GET | `/payslips/{id}` | `payslips.show` | View payslip |
| GET | `/payslips/{id}/pdf` | `payslips.pdf` | View as PDF |
| GET | `/payslips/{id}/download` | `payslips.download` | Download PDF |

### Time Tracking

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/time-tracking` | `time-tracking.index` | Overview |
| GET | `/time-tracking/manual` | `time-tracking.manual` | Manual entry form |
| POST | `/time-tracking/{employee}/sign-in` | `time-tracking.sign-in` | Sign in employee |
| POST | `/time-tracking/{employee}/sign-out` | `time-tracking.sign-out` | Sign out employee |
| POST | `/time-tracking/entries` | `time-tracking.entries.store` | Create entry |
| PUT | `/time-tracking/entries/{id}` | `time-tracking.entries.update` | Update entry |
| DELETE | `/time-tracking/entries/{id}` | `time-tracking.entries.destroy` | Delete entry |
| GET | `/time-tracking/status` | `time-tracking.status` | Today's status |

### Leave Management

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/leave` | `leave.index` | List leave entries |
| GET | `/leave/create` | `leave.create` | Create form |
| POST | `/leave` | `leave.store` | Create entry |
| GET | `/leave/{id}` | `leave.show` | View entry |
| GET | `/leave/{id}/edit` | `leave.edit` | Edit form |
| PUT | `/leave/{id}` | `leave.update` | Update entry |
| DELETE | `/leave/{id}` | `leave.destroy` | Delete entry |

### Compliance

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/compliance` | `compliance.index` | Dashboard |
| GET | `/compliance/uif` | `compliance.uif.index` | UIF declarations |
| POST | `/compliance/uif/generate` | `compliance.uif.generate` | Generate UI-19 |
| GET | `/compliance/uif/{id}/edit` | `compliance.uif.edit` | Edit UI-19 |
| PUT | `/compliance/uif/{id}` | `compliance.uif.update` | Update UI-19 |
| GET | `/compliance/uif/{id}/download` | `compliance.uif.download` | Download CSV |
| GET | `/compliance/emp201` | `compliance.emp201.index` | EMP201 submissions |
| POST | `/compliance/emp201/generate` | `compliance.emp201.generate` | Generate EMP201 |
| GET | `/compliance/emp201/{id}/edit` | `compliance.emp201.edit` | Edit EMP201 |
| PUT | `/compliance/emp201/{id}` | `compliance.emp201.update` | Update EMP201 |
| GET | `/compliance/emp201/{id}/download` | `compliance.emp201.download` | Download CSV |
| GET | `/compliance/irp5` | `compliance.irp5.index` | IRP5 certificates |
| POST | `/compliance/irp5/generate/{employee}` | `compliance.irp5.generate` | Generate IRP5 |
| POST | `/compliance/irp5/generate-bulk` | `compliance.irp5.generate-bulk` | Bulk generate |
| GET | `/compliance/irp5/{id}/edit` | `compliance.irp5.edit` | Edit IRP5 |
| PUT | `/compliance/irp5/{id}` | `compliance.irp5.update` | Update IRP5 |
| GET | `/compliance/irp5/{id}/download` | `compliance.irp5.download` | Download PDF |
| GET | `/compliance/sars-export` | `compliance.sars.export` | SARS export page |
| POST | `/compliance/{id}/mark-submitted` | `compliance.mark-submitted` | Mark submitted |

### Reports

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/reports` | `reports.index` | Reports dashboard |
| GET | `/reports/export/csv` | `reports.export.csv` | Export CSV |
| GET | `/reports/export/excel` | `reports.export.excel` | Export Excel |
| GET | `/reports/export/pdf` | `reports.export.pdf` | Export PDF |

**Query Parameters:**
- `type`: `payments`, `payroll`, `all`
- `start_date`: Start of date range
- `end_date`: End of date range
- `status`: Filter by status

### Audit Logs

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/audit-logs` | `audit-logs.index` | List logs |
| GET | `/audit-logs/{id}` | `audit-logs.show` | View log details |

### Templates

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/templates` | `templates.index` | List templates |
| GET | `/templates/{type}` | `templates.show` | View/edit template |
| PUT | `/templates/{type}` | `templates.update` | Update template |
| POST | `/templates/{type}/reset` | `templates.reset` | Reset to default |
| GET | `/templates/{type}/preview` | `templates.preview` | Preview template |
| POST | `/templates/{type}/preset` | `templates.load-preset` | Load preset |

### Escrow

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/escrow/deposit` | `escrow.deposit.index` | Deposit page |
| POST | `/escrow/deposit` | `escrow.deposit.store` | Create deposit |
| GET | `/escrow/deposit/{id}` | `escrow.deposit.show` | View deposit |

### Billing

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/billing` | `billing.index` | Billing overview |
| GET | `/billing/{id}` | `billing.show` | Transaction details |

### Admin Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/escrow` | `admin.escrow.index` | Escrow management |
| POST | `/admin/escrow/deposits` | `admin.escrow.deposits.create` | Create deposit |
| POST | `/admin/escrow/deposits/{id}/confirm` | `admin.escrow.deposits.confirm` | Confirm deposit |
| POST | `/admin/escrow/payments/{id}/fee-release` | `admin.escrow.payments.fee-release` | Release fee |
| POST | `/admin/escrow/payments/{id}/fund-return` | `admin.escrow.payments.fund-return` | Return funds |
| GET | `/admin/escrow/balances` | `admin.escrow.balances` | View all balances |

### Settings

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/settings/profile` | `settings.profile` | Profile settings |
| PUT | `/settings/profile` | `settings.profile.update` | Update profile |
| POST | `/settings/profile/send-email-otp` | `settings.profile.send-email-otp` | Send email OTP |
| POST | `/settings/profile/verify-email-otp` | `settings.profile.verify-email-otp` | Verify email |
| GET | `/settings/password` | `settings.password` | Password settings |
| PUT | `/settings/password` | `settings.password.update` | Update password |
| GET | `/settings/appearance` | `settings.appearance` | Appearance settings |
| GET | `/settings/two-factor` | `settings.two-factor` | 2FA settings |
| POST | `/settings/two-factor/enable` | `settings.two-factor.enable` | Enable 2FA |
| POST | `/settings/two-factor/disable` | `settings.two-factor.disable` | Disable 2FA |
| POST | `/settings/two-factor/confirm` | `settings.two-factor.confirm` | Confirm 2FA |
| GET | `/settings/two-factor/recovery-codes` | `settings.two-factor.recovery-codes` | Get codes |
| POST | `/settings/two-factor/recovery-codes` | `settings.two-factor.recovery-codes.regenerate` | Regenerate codes |

## Error Responses

### Standard Error Format

```typescript
{
  message: string,
  errors?: {
    [field: string]: string[]
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 302 | Redirect |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/employee/send-otp` | 10 requests / 15 minutes |
| `/employee/verify-otp` | 20 requests / 15 minutes |
| `/email/verify/*` | 6 requests / 1 minute |
| Default | 60 requests / 1 minute |

## Inertia.js

All routes return Inertia responses for SPA navigation. The response includes:

```typescript
{
  component: string,     // React component path
  props: object,         // Page props
  url: string,          // Current URL
  version: string       // Asset version
}
```

For AJAX requests with `X-Inertia` header, returns JSON. Otherwise returns full HTML page.
