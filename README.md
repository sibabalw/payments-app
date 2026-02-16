# SwiftPay - Payments & Payroll Management System

A comprehensive Laravel-based payment scheduling and payroll management application built for South African businesses. SwiftPay enables businesses to manage payments, process payroll with full tax compliance, track employee time, and handle escrow-based fund management.

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Core Modules](#core-modules)
- [Scheduled Jobs](#scheduled-jobs)
- [API & Routes](#api--routes)
- [Testing](#testing)
- [Deployment](#deployment)
- [Contributing](#contributing)

## Features

### Business Management
- Multi-business support with role-based access
- Business onboarding workflow
- Business status management (active, suspended, banned)
- Bank account configuration
- Custom email templates per business

### Payment Scheduling
- One-time and recurring payments
- Multiple recipient support per schedule
- Flexible scheduling (weekly, bi-weekly, monthly, custom cron)
- Payment status tracking (pending, processing, succeeded, failed)
- Payment reminders

### Payroll Management
- Employee management with tax profiles
- Automated salary calculations with SA tax compliance
- PAYE, UIF, and SDL deductions
- Custom deductions support
- Payroll schedules (weekly, bi-weekly, monthly)
- Digital payslips with PDF generation

### South African Tax Compliance
- **PAYE**: Automatic tax bracket calculations
- **UIF**: Employee and employer contribution calculations (1% each, capped)
- **SDL**: Skills Development Levy (1% of payroll)
- **UI-19 Declarations**: Monthly UIF reports
- **EMP201 Submissions**: Monthly tax reconciliation
- **IRP5 Certificates**: Annual tax certificates

### Time & Attendance
- Employee time tracking (sign-in/sign-out)
- Manual time entry for managers
- Overtime calculations
- Leave management
- Employee self-service portal (OTP-based access)

### Escrow Management
- Business escrow accounts
- Deposit tracking and confirmation
- Balance monitoring
- Automatic low-balance warnings
- Fee management

### Reporting & Analytics
- Dashboard with financial metrics
- Payment and payroll trends
- Success rate tracking
- CSV/Excel/PDF exports
- Audit logging

## Tech Stack

### Backend
- **PHP 8.2+**
- **Laravel 12** - Web framework
- **Laravel Fortify** - Authentication
- **Laravel Socialite** - Google OAuth
- **DomPDF** - PDF generation

### Frontend
- **React 19** - UI library
- **Inertia.js v2** - SPA bridge
- **TypeScript** - Type safety
- **Tailwind CSS v4** - Styling
- **Radix UI** - Accessible components
- **Recharts** - Data visualization
- **Lucide React** - Icons

### Database & Queue
- **MySQL/MariaDB** - Primary database
- **Redis** (optional) - Caching and queues
- **Laravel Queue** - Background job processing

### Development Tools
- **Pest v4** - Testing framework
- **Laravel Pint** - Code formatting
- **ESLint** - JavaScript linting
- **Prettier** - Code formatting
- **Vite** - Asset bundling

## Requirements

- PHP >= 8.2
- Composer >= 2.0
- Node.js >= 18.0
- MySQL >= 8.0 or MariaDB >= 10.6
- Redis (optional, for caching/queues)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd payments-app
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database

Edit `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payments_app
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Build Assets

```bash
npm run build
```

### 7. Start Development Server

```bash
composer run dev
```

This starts:
- Laravel server (`php artisan serve`)
- Queue worker (`php artisan queue:listen`)
- Log viewer (`php artisan pail`)
- Vite dev server (`npm run dev`)

## Configuration

### Key Configuration Files

| File | Purpose |
|------|---------|
| `config/escrow.php` | Escrow account settings |
| `config/payment.php` | Payment gateway configuration |
| `config/billing.php` | Billing and subscription settings |
| `config/features.php` | Feature flags |
| `config/locks.php` | Distributed locking (Redis/Database) |
| `config/idempotency.php` | Idempotency service settings |

### Environment Variables

```env
# Application
APP_NAME="SwiftPay"
APP_URL=http://localhost

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

# Queue
QUEUE_CONNECTION=database

# Escrow Settings
ESCROW_FEE_PERCENTAGE=2.5
ESCROW_MIN_BALANCE_WARNING=1000
```

## Architecture

### Directory Structure

```
app/
├── Actions/              # Fortify authentication actions
├── Console/Commands/     # Artisan commands
├── Http/
│   ├── Controllers/      # Request handlers
│   ├── Middleware/       # HTTP middleware
│   └── Requests/         # Form request validation
├── Jobs/                 # Queue jobs
├── Listeners/            # Event listeners
├── Mail/                 # Mailable classes
├── Models/               # Eloquent models
├── Notifications/        # Notification classes
├── Observers/            # Model observers
├── Policies/             # Authorization policies
├── Providers/            # Service providers
├── Rules/                # Custom validation rules
└── Services/             # Business logic services
    ├── BillingGateway/   # Billing integrations
    ├── Idempotency/      # Idempotent request handling
    ├── Locks/            # Distributed locking
    └── PaymentGateway/   # Payment integrations

resources/js/
├── components/           # Reusable React components
│   └── ui/              # Shadcn/Radix UI components
├── hooks/               # Custom React hooks
├── layouts/             # Page layouts
├── lib/                 # Utility functions
├── pages/               # Inertia page components
└── types/               # TypeScript type definitions
```

### Key Models & Relationships

```
User
├── ownedBusinesses (HasMany)
└── businesses (BelongsToMany via business_user)

Business
├── owner (BelongsTo User)
├── users (BelongsToMany)
├── paymentSchedules (HasMany)
├── payrollSchedules (HasMany - via PayrollSchedule)
├── employees (HasMany)
├── recipients (HasMany)
├── escrowDeposits (HasMany)
└── templates (HasMany)

PaymentSchedule
├── business (BelongsTo)
├── recipients (BelongsToMany)
└── paymentJobs (HasMany)

PayrollSchedule
├── business (BelongsTo)
├── employees (BelongsToMany)
└── payrollJobs (HasMany)

Employee
├── business (BelongsTo)
├── payrollSchedules (BelongsToMany)
├── payrollJobs (HasMany)
├── customDeductions (HasMany)
├── timeEntries (HasMany)
└── leaveEntries (HasMany)
```

## Core Modules

### 1. Payment Processing

**Service**: `App\Services\PaymentService`

Handles payment execution with:
- Idempotency protection
- Distributed locking
- Escrow balance validation
- Fee calculation
- Status tracking

**Command**: `php artisan payments:process-scheduled`

### 2. Payroll Processing

**Service**: `App\Services\SalaryCalculationService`

Calculates:
- Gross salary
- Overtime pay
- Tax deductions (PAYE, UIF, SDL)
- Custom deductions
- Net salary

**Tax Service**: `App\Services\SouthAfricanTaxService`

Implements SA tax brackets and calculations for the current tax year.

**Command**: `php artisan payroll:process-scheduled`

### 3. Compliance Management

**Services**:
- `App\Services\UIFDeclarationService` - UI-19 generation
- `App\Services\EMP201Service` - EMP201 reconciliation
- `App\Services\IRP5Service` - IRP5 certificates

**Features**:
- Generate compliance documents from payroll data
- Edit documents before submission
- Export to SARS-compatible formats (CSV)
- Track submission status

### 4. Escrow Management

**Service**: `App\Services\EscrowService`

Manages:
- Balance tracking
- Deposit processing
- Fund reservation for payments
- Low balance alerts

### 5. Time Tracking

**Controller**: `App\Http\Controllers\TimeEntryController`

Features:
- Real-time sign-in/sign-out
- Geolocation tracking (optional)
- Break time tracking
- Overtime flagging

### 6. Employee Self-Service

**Controller**: `App\Http\Controllers\EmployeeSignInController`

OTP-based employee portal for:
- Time tracking
- Viewing payslips
- Leave requests

## Scheduled Jobs

Configured in `routes/console.php`:

| Schedule | Command/Job | Description |
|----------|-------------|-------------|
| Every minute | `payments:process-scheduled` | Process due payment schedules |
| Every minute | `payroll:process-scheduled` | Process due payroll schedules |
| Every 15 min | `payments:send-reminders` | Send payment reminders |
| Every 5 min | `jobs:sync` | Sync pending jobs with queue |
| Daily 00:00 | `CheckEscrowBalanceJob` | Check escrow balances and send warnings |

### Setup Cron

Add to your server's crontab:

```bash
* * * * * cd /path/to/payments-app && php artisan schedule:run >> /dev/null 2>&1
```

### Manual Execution

```bash
# Check escrow balances
php artisan escrow:check-balance --sync

# Process payments
php artisan payments:process-scheduled

# Process payroll
php artisan payroll:process-scheduled
```

## API & Routes

### Public Routes

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/` | Home page |
| GET | `/features` | Features page |
| GET | `/pricing` | Pricing page |
| GET | `/about` | About page |
| GET | `/contact` | Contact page |

### Authentication

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/login` | Login page |
| POST | `/login` | Authenticate |
| GET | `/register` | Registration page |
| POST | `/register` | Create account |
| GET | `/auth/google` | Google OAuth |
| GET | `/forgot-password` | Password reset |

### Employee Portal

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/employee/sign-in` | Employee OTP login |
| POST | `/employee/send-otp` | Send OTP to employee |
| POST | `/employee/verify-otp` | Verify OTP |
| GET | `/employee/time-tracking` | Time tracking portal |

### Protected Routes (Auth Required)

#### Dashboard
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/dashboard` | Main dashboard |

#### Businesses
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/businesses` | List businesses |
| POST | `/businesses` | Create business |
| GET | `/businesses/{id}/edit` | Edit business |
| PUT | `/businesses/{id}` | Update business |
| POST | `/businesses/{id}/switch` | Switch active business |

#### Payments
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/payments` | List payment schedules |
| POST | `/payments` | Create schedule |
| GET | `/payments/jobs` | List payment jobs |
| POST | `/payments/{id}/pause` | Pause schedule |
| POST | `/payments/{id}/resume` | Resume schedule |

#### Payroll
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/payroll` | List payroll schedules |
| POST | `/payroll` | Create schedule |
| GET | `/payroll/jobs` | List payroll jobs |
| GET | `/payslips` | List payslips |
| GET | `/payslips/{id}/pdf` | Download payslip PDF |

#### Employees
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/employees` | List employees |
| POST | `/employees` | Create employee |
| GET | `/employees/{id}/schedule` | Employee work schedule |
| GET | `/employees/{id}/payslips` | Employee payslips |

#### Compliance
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/compliance` | Compliance dashboard |
| GET | `/compliance/uif` | UIF declarations |
| POST | `/compliance/uif/generate` | Generate UI-19 |
| GET | `/compliance/emp201` | EMP201 submissions |
| GET | `/compliance/irp5` | IRP5 certificates |
| GET | `/compliance/sars-export` | SARS export files |

#### Time Tracking
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/time-tracking` | Time tracking overview |
| POST | `/time-tracking/{emp}/sign-in` | Sign in employee |
| POST | `/time-tracking/{emp}/sign-out` | Sign out employee |

#### Reports
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/reports` | Reports dashboard |
| GET | `/reports/export/csv` | Export CSV |
| GET | `/reports/export/pdf` | Export PDF |

## Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Tests

```bash
# Feature tests only
php artisan test --testsuite=Feature

# Specific file
php artisan test tests/Feature/ComplianceTest.php

# Filter by name
php artisan test --filter=testEscrowBalance
```

### Test Coverage

```bash
php artisan test --coverage
```

### Code Formatting

```bash
# PHP (Pint)
vendor/bin/pint

# JavaScript/TypeScript (Prettier)
npm run format

# Linting
npm run lint
```

## Deployment

### Production Checklist

1. **Environment**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Optimize**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```

3. **Database**
   ```bash
   php artisan migrate --force
   ```

4. **Queue Worker**
   ```bash
   php artisan queue:work --daemon
   ```
   
   Or use Supervisor (configs in `supervisor/` directory)

5. **Scheduler**
   ```bash
   # Add to crontab
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```

### Supervisor Configuration

Example configs are in `supervisor/`:

```ini
[program:payments-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests (`php artisan test`)
4. Run linting (`vendor/bin/pint && npm run lint`)
5. Commit changes (`git commit -m 'Add amazing feature'`)
6. Push to branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Code Standards

- Follow PSR-12 for PHP
- Use TypeScript for all frontend code
- Write tests for new features
- Document public methods

## License

This project is proprietary software. All rights reserved.

---

Built with Laravel, React, and Inertia.js
