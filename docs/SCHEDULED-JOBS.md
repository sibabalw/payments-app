# Scheduled Jobs & Background Processing

This document details all scheduled jobs, queue workers, and background processing in SwiftPay.

## Overview

SwiftPay uses Laravel's task scheduling and queue system for:

- Processing scheduled payments
- Processing scheduled payroll
- Sending payment reminders
- Checking escrow balances
- Syncing job states

**For payroll and payment schedules to run on time you need both:**

1. **Queue worker** — processes jobs already in the queue (e.g. `php artisan queue:work database --queue=high,default`).
2. **Scheduler** — runs every minute and executes due tasks (e.g. `payroll:process-scheduled`), which then **dispatches** payroll/payment jobs into the queue.

If only the queue worker is running, jobs that are already in the queue will run, but **no new payroll or payment runs will be created** until the scheduler runs. In development, run the scheduler with `php artisan schedule:work` in a separate terminal, or use `composer run dev` which starts both the queue worker and the scheduler.

## Scheduler Setup

### Cron Entry

Add this to your server's crontab:

```bash
* * * * * cd /path/to/payments-app && php artisan schedule:run >> /dev/null 2>&1
```

### Verify Scheduler

```bash
# List all scheduled tasks
php artisan schedule:list

# Test scheduler execution
php artisan schedule:test
```

## Scheduled Tasks

All schedules are defined in `routes/console.php`.

### 1. Process Scheduled Payments

**Schedule**: Every minute
**Command**: `php artisan payments:process-scheduled`

Processes payment schedules that are due (`next_run_at <= now()`).

**What it does**:
1. Finds all active payment schedules with `next_run_at` in the past
2. For each schedule, creates `PaymentJob` records for each recipient
3. Dispatches jobs to the queue for processing
4. Updates `next_run_at` based on frequency

**Manual execution**:
```bash
php artisan payments:process-scheduled
```

### 2. Process Scheduled Payroll

**Schedule**: Every minute
**Command**: `php artisan payroll:process-scheduled`

Processes payroll schedules that are due.

**What it does**:
1. Finds all active payroll schedules with `next_run_at` in the past
2. For each schedule, creates `PayrollJob` records for each employee
3. Calculates salary, deductions, and net pay
4. Dispatches jobs to the queue for processing
5. Updates `next_run_at` based on frequency

**Manual execution**:
```bash
php artisan payroll:process-scheduled
```

### 3. Send Payment Reminders

**Schedule**: Every 15 minutes
**Command**: `php artisan payments:send-reminders`

Sends email reminders for upcoming payments.

**What it does**:
1. Finds payments scheduled in the next 24-48 hours
2. Sends reminder emails to business owners
3. Tracks which reminders have been sent

**Manual execution**:
```bash
php artisan payments:send-reminders
```

### 4. Sync Jobs

**Schedule**: Every 5 minutes
**Command**: `php artisan jobs:sync`

Ensures pending jobs are properly synced with the queue.

**What it does**:
1. Finds jobs with `pending` status that aren't in the queue
2. Re-dispatches them to the queue
3. Acts as a backup in case boot-time sync fails

**Manual execution**:
```bash
php artisan jobs:sync
```

### 5. Check Escrow Balance (Daily)

**Schedule**: Daily at 00:00 (midnight)
**Job**: `App\Jobs\CheckEscrowBalanceJob`

Checks if upcoming payments and payroll exceed escrow balance.

**What it does**:
1. Iterates through all active businesses
2. Calculates upcoming payments (next 7 days)
3. Calculates upcoming payroll (next 7 days)
4. If total exceeds escrow balance, sends warning email
5. Logs all findings

**Manual execution**:
```bash
# Via queue (recommended)
php artisan escrow:check-balance

# Synchronous (for testing)
php artisan escrow:check-balance --sync
```

**Email sent**: `App\Mail\EscrowBalanceWarningEmail`

## Queue Jobs

### ProcessPaymentJob

**File**: `app/Jobs/ProcessPaymentJob.php`

Processes a single payment job.

**Features**:
- Idempotency protection
- Distributed locking
- Retry on failure (3 attempts)
- Backoff: 60 seconds between retries

**Flow**:
1. Acquire lock for payment
2. Check idempotency key
3. Validate escrow balance
4. Process payment via gateway
5. Deduct from escrow
6. Update job status
7. Send notification email

### ProcessPayrollJob

**File**: `app/Jobs/ProcessPayrollJob.php`

Processes a single payroll job.

**Features**:
- Salary calculation with tax compliance
- Custom deduction handling
- Retry on failure (3 attempts)
- Backoff: 60 seconds between retries

**Flow**:
1. Calculate gross salary
2. Calculate overtime pay
3. Apply tax deductions (PAYE, UIF, SDL)
4. Apply custom deductions
5. Calculate net pay
6. Process payment via gateway
7. Deduct from escrow
8. Update job status
9. Generate payslip
10. Send notification email

### CheckEscrowBalanceJob

**File**: `app/Jobs/CheckEscrowBalanceJob.php`

Daily escrow balance check.

**Features**:
- Checks all active businesses
- 7-day lookahead for payments/payroll
- Sends warning emails
- Comprehensive logging
- Retry on failure (3 attempts)

## Queue Configuration

### Database Queue (Default)

```env
QUEUE_CONNECTION=database
```

Jobs are stored in the `jobs` table.

### Redis Queue (Recommended for Production)

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Queue Worker

Payroll jobs use the `high` queue; the worker must listen on it (e.g. `--queue=high,default`).

**Development** (run both so payroll/payments run on time):
```bash
# Terminal 1: queue worker (processes jobs)
php artisan queue:work database --queue=high,default
# or
php artisan queue:listen database --queue=high,default --tries=3

# Terminal 2: scheduler (dispatches payroll/payment jobs every minute)
php artisan schedule:work
```
Or use `composer run dev` to start server, queue worker, scheduler, pail, and Vite together.

**Production** (Supervisor recommended):
```bash
php artisan queue:work database --queue=high,default --sleep=3 --tries=3 --max-time=3600
```
Production also needs a cron entry so the scheduler runs every minute (see Scheduler Setup).

## Supervisor Configuration

Supervisor configs are in `supervisor/` directory.

### Queue Worker

```ini
[program:payments-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/payments-app/artisan queue:work database --queue=high,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/payments-app/storage/logs/worker.log
stopwaitsecs=3600
```

### Scheduler

```ini
[program:payments-scheduler]
command=/bin/bash -c "while [ true ]; do php /var/www/payments-app/artisan schedule:run --verbose --no-interaction >> /var/www/payments-app/storage/logs/scheduler.log 2>&1; sleep 60; done"
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/payments-app/storage/logs/scheduler.log
```

### Supervisor Commands

```bash
# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start/Stop workers
sudo supervisorctl start payments-queue:*
sudo supervisorctl stop payments-queue:*

# Check status
sudo supervisorctl status
```

## Artisan Commands

### Available Commands

```bash
# Payments
php artisan payments:process-scheduled    # Process due payments
php artisan payments:send-reminders       # Send payment reminders

# Payroll
php artisan payroll:process-scheduled     # Process due payroll

# Escrow
php artisan escrow:check-balance          # Check escrow balances

# Jobs
php artisan jobs:sync                     # Sync pending jobs

# Billing
php artisan billing:calculate-monthly     # Calculate monthly billing
php artisan billing:charge-subscriptions  # Charge subscriptions

# Reports
php artisan reports:generate-reconciliation  # Generate bank reconciliation
```

### Command Options

```bash
# Escrow check with sync option
php artisan escrow:check-balance --sync   # Run synchronously
php artisan escrow:check-balance          # Dispatch to queue
```

## Monitoring

### Job Status

Check job status in the database:

```sql
-- Pending jobs in queue
SELECT * FROM jobs WHERE queue = 'default';

-- Failed jobs
SELECT * FROM failed_jobs ORDER BY failed_at DESC;

-- Payment job statuses
SELECT status, COUNT(*) FROM payment_jobs GROUP BY status;

-- Payroll job statuses
SELECT status, COUNT(*) FROM payroll_jobs GROUP BY status;
```

### Laravel Horizon (Optional)

For advanced queue monitoring, consider [Laravel Horizon](https://laravel.com/docs/horizon).

### Logs

Check logs for scheduled tasks:

```bash
# Application log
tail -f storage/logs/laravel.log

# Pail (real-time log viewer)
php artisan pail
```

## Troubleshooting

### Payroll / payments did not run at scheduled time

Payroll and payment runs are **dispatched** by the scheduler (every minute). If only the queue worker is running, no new runs are created.

- **Fix**: Run the scheduler. In development: `php artisan schedule:work` in a separate terminal, or use `composer run dev` (which starts both queue worker and scheduler).
- **Production**: Ensure cron runs `* * * * * cd /path/to/app && php artisan schedule:run` every minute.

### Jobs Not Processing

1. **Check queue worker** (must listen on `high` for payroll):
   ```bash
   php artisan queue:work database --queue=high,default --verbose
   ```

2. **Check failed jobs**:
   ```bash
   php artisan queue:failed
   ```

3. **Retry failed jobs**:
   ```bash
   php artisan queue:retry all
   ```

### Scheduler Not Running

1. **Check cron**:
   ```bash
   crontab -l | grep schedule
   ```

2. **Test manually**:
   ```bash
   php artisan schedule:run --verbose
   ```

3. **Check logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep schedule
   ```

### Escrow Check Not Sending Emails

1. **Check mail config**:
   ```bash
   php artisan tinker
   >>> Mail::raw('Test', fn($m) => $m->to('test@example.com'));
   ```

2. **Run with logging**:
   ```bash
   php artisan escrow:check-balance --sync
   tail -f storage/logs/laravel.log
   ```

## Persistence

All scheduled tasks are defined in code (`routes/console.php`) and persist across:

- Server restarts
- Code deployments
- Database resets

The scheduler only requires:
1. The cron entry pointing to `schedule:run`
2. A running queue worker for queued jobs
