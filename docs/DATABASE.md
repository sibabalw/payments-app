# Database Schema Documentation

This document describes the database schema for Swift Pay.

## Entity Relationship Diagram

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│     users       │────<│  business_user  │>────│   businesses    │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                                               │
        │                                               │
        │ owns                                          │ has many
        │                                               │
        v                                               v
┌─────────────────┐                           ┌─────────────────┐
│   businesses    │                           │    employees    │
└─────────────────┘                           └─────────────────┘
        │                                               │
        │ has many                                      │
        │                                               │
        v                                               v
┌─────────────────┐     ┌─────────────────┐   ┌─────────────────┐
│payment_schedules│────<│payment_schedule_│   │  payroll_jobs   │
└─────────────────┘     │    recipient    │   └─────────────────┘
        │               └─────────────────┘
        │                       │
        │                       v
        │               ┌─────────────────┐
        │               │   recipients    │
        v               └─────────────────┘
┌─────────────────┐
│  payment_jobs   │
└─────────────────┘
```

## Core Tables

### users

Stores user account information.

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NULL,
    remember_token VARCHAR(100) NULL,
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,
    google_id VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    current_business_id BIGINT NULL,
    onboarding_completed_at TIMESTAMP NULL,
    email_preferences JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (current_business_id) REFERENCES businesses(id)
);
```

### businesses

Stores business/company information.

```sql
CREATE TABLE businesses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    logo VARCHAR(255) NULL,
    business_type VARCHAR(50) NULL,
    status VARCHAR(20) DEFAULT 'active',
    status_reason TEXT NULL,
    status_changed_at TIMESTAMP NULL,
    registration_number VARCHAR(255) NULL,
    tax_id VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    street_address VARCHAR(255) NULL,
    city VARCHAR(255) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'South Africa',
    description TEXT NULL,
    contact_person_name VARCHAR(255) NULL,
    escrow_balance DECIMAL(15,2) DEFAULT 0.00,
    bank_account_details JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Status Values**: `active`, `suspended`, `banned`

### business_user

Pivot table for business-user relationships (beyond ownership).

```sql
CREATE TABLE business_user (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (business_id, user_id)
);
```

## Employee Management

### employees

Stores employee information for payroll.

```sql
CREATE TABLE employees (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    id_number VARCHAR(13) NULL,
    tax_number VARCHAR(20) NULL,
    employment_type VARCHAR(50) DEFAULT 'full_time',
    hours_worked_per_month DECIMAL(5,2) NULL,
    department VARCHAR(255) NULL,
    start_date DATE NULL,
    gross_salary DECIMAL(15,2) NOT NULL,
    hourly_rate DECIMAL(10,2) NULL,
    overtime_rate_multiplier DECIMAL(4,2) DEFAULT 1.50,
    weekend_rate_multiplier DECIMAL(4,2) DEFAULT 2.00,
    holiday_rate_multiplier DECIMAL(4,2) DEFAULT 2.00,
    bank_account_details JSON NULL,
    tax_status VARCHAR(50) DEFAULT 'standard',
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

**Employment Types**: `full_time`, `part_time`, `contractor`, `temporary`

### custom_deductions

Custom deductions for employees (pension, medical aid, etc.).

```sql
CREATE TABLE custom_deductions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    employee_id BIGINT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NULL,
    percentage DECIMAL(5,2) NULL,
    is_pre_tax BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

**Deduction Types**: `fixed`, `percentage`

## Payment Scheduling

### recipients

Payment recipients (vendors, suppliers, etc.).

```sql
CREATE TABLE recipients (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    bank_name VARCHAR(255) NULL,
    account_number VARCHAR(255) NULL,
    branch_code VARCHAR(50) NULL,
    account_type VARCHAR(50) NULL,
    reference VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

### payment_schedules

Payment schedule definitions.

```sql
CREATE TABLE payment_schedules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    type VARCHAR(50) DEFAULT 'payment',
    name VARCHAR(255) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    status VARCHAR(20) DEFAULT 'active',
    schedule_type VARCHAR(20) DEFAULT 'recurring',
    next_run_at TIMESTAMP NULL,
    last_run_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

**Status Values**: `active`, `paused`, `cancelled`, `completed`
**Schedule Types**: `one_time`, `recurring`
**Frequencies**: `weekly`, `bi-weekly`, `monthly`, `quarterly`, `annually`, `custom`

### payment_schedule_recipient

Pivot table linking schedules to recipients.

```sql
CREATE TABLE payment_schedule_recipient (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_schedule_id BIGINT NOT NULL,
    recipient_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (payment_schedule_id) REFERENCES payment_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE
);
```

### payment_jobs

Individual payment job records.

```sql
CREATE TABLE payment_jobs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_schedule_id BIGINT NOT NULL,
    recipient_id BIGINT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    fee DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    idempotency_key VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (payment_schedule_id) REFERENCES payment_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE
);
```

**Status Values**: `pending`, `processing`, `succeeded`, `failed`

## Payroll

### payroll_schedules

Payroll schedule definitions.

```sql
CREATE TABLE payroll_schedules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    schedule_type VARCHAR(20) DEFAULT 'recurring',
    status VARCHAR(20) DEFAULT 'active',
    next_run_at TIMESTAMP NULL,
    last_run_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

### payroll_schedule_employee

Pivot table linking payroll schedules to employees.

```sql
CREATE TABLE payroll_schedule_employee (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payroll_schedule_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (payroll_schedule_id) REFERENCES payroll_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

### payroll_jobs

Individual payroll job records (payslips).

```sql
CREATE TABLE payroll_jobs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payroll_schedule_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    gross_salary DECIMAL(15,2) NOT NULL,
    overtime_pay DECIMAL(15,2) DEFAULT 0.00,
    paye DECIMAL(15,2) DEFAULT 0.00,
    uif_employee DECIMAL(15,2) DEFAULT 0.00,
    uif_employer DECIMAL(15,2) DEFAULT 0.00,
    sdl DECIMAL(15,2) DEFAULT 0.00,
    custom_deductions JSON NULL,
    net_salary DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    fee DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (payroll_schedule_id) REFERENCES payroll_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

## Time & Attendance

### time_entries

Employee time tracking records.

```sql
CREATE TABLE time_entries (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    sign_in_at TIMESTAMP NOT NULL,
    sign_out_at TIMESTAMP NULL,
    break_duration_minutes INT DEFAULT 0,
    notes TEXT NULL,
    is_overtime BOOLEAN DEFAULT FALSE,
    is_weekend BOOLEAN DEFAULT FALSE,
    is_holiday BOOLEAN DEFAULT FALSE,
    location_lat DECIMAL(10,8) NULL,
    location_lng DECIMAL(11,8) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

### leave_entries

Employee leave/time-off records.

```sql
CREATE TABLE leave_entries (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT NULL,
    approved_by BIGINT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id)
);
```

**Leave Types**: `annual`, `sick`, `family`, `maternity`, `paternity`, `unpaid`, `study`
**Status Values**: `pending`, `approved`, `rejected`, `cancelled`

### employee_schedules

Employee work schedule templates.

```sql
CREATE TABLE employee_schedules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

## Escrow & Billing

### escrow_deposits

Business escrow deposit records.

```sql
CREATE TABLE escrow_deposits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    reference VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL,
    confirmed_by BIGINT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id)
);
```

### billing_transactions

Platform billing transactions.

```sql
CREATE TABLE billing_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    description TEXT NULL,
    reference VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

### monthly_billings

Monthly billing summaries.

```sql
CREATE TABLE monthly_billings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    period VARCHAR(7) NOT NULL,
    payment_count INT DEFAULT 0,
    payroll_count INT DEFAULT 0,
    total_fees DECIMAL(15,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

## Compliance

### compliance_submissions

Compliance document submissions (UI-19, EMP201, IRP5).

```sql
CREATE TABLE compliance_submissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    employee_id BIGINT NULL,
    type VARCHAR(50) NOT NULL,
    period VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    data JSON NULL,
    file_path VARCHAR(255) NULL,
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    
    INDEX (business_id, type, period)
);
```

**Types**: `ui19`, `emp201`, `irp5`
**Status Values**: `draft`, `generated`, `submitted`

## Templates

### business_templates

Custom email/document templates per business.

```sql
CREATE TABLE business_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    content TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
```

### template_blocks

Template content blocks for the template editor.

```sql
CREATE TABLE template_blocks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    template_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL,
    order INT DEFAULT 0,
    content TEXT NULL,
    properties JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (template_id) REFERENCES business_templates(id) ON DELETE CASCADE
);
```

## Audit & Logging

### audit_logs

System audit trail.

```sql
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT NULL,
    user_id BIGINT NULL,
    action VARCHAR(255) NOT NULL,
    auditable_type VARCHAR(255) NOT NULL,
    auditable_id BIGINT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX (auditable_type, auditable_id),
    INDEX (user_id),
    INDEX (business_id)
);
```

## Queue Tables

### jobs

Laravel queue jobs table.

```sql
CREATE TABLE jobs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT NOT NULL,
    reserved_at INT NULL,
    available_at INT NOT NULL,
    created_at INT NOT NULL,
    
    INDEX (queue)
);
```

### failed_jobs

Failed queue jobs.

```sql
CREATE TABLE failed_jobs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Indexes

Key indexes for performance:

```sql
-- Payment lookups
CREATE INDEX idx_payment_schedules_business_status ON payment_schedules(business_id, status);
CREATE INDEX idx_payment_schedules_next_run ON payment_schedules(next_run_at);
CREATE INDEX idx_payment_jobs_schedule_status ON payment_jobs(payment_schedule_id, status);

-- Payroll lookups
CREATE INDEX idx_payroll_schedules_business_status ON payroll_schedules(business_id, status);
CREATE INDEX idx_payroll_schedules_next_run ON payroll_schedules(next_run_at);
CREATE INDEX idx_payroll_jobs_schedule_status ON payroll_jobs(payroll_schedule_id, status);

-- Employee lookups
CREATE INDEX idx_employees_business ON employees(business_id);
CREATE INDEX idx_time_entries_employee ON time_entries(employee_id, sign_in_at);

-- Compliance
CREATE INDEX idx_compliance_business_type_period ON compliance_submissions(business_id, type, period);
```

## Migrations

All migrations are in `database/migrations/`. Run with:

```bash
# Run migrations
php artisan migrate

# Fresh start (drops all tables)
php artisan migrate:fresh

# Rollback last batch
php artisan migrate:rollback

# Check status
php artisan migrate:status
```
