<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .company-info h1 {
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        .company-info p {
            margin: 2px 0;
            font-size: 10px;
            color: #666;
        }
        .payslip-title {
            text-align: right;
        }
        .payslip-title h2 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .payslip-title p {
            margin: 2px 0;
            font-size: 10px;
            color: #666;
        }
        .section {
            margin-bottom: 12px;
        }
        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 6px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 10px;
        }
        .info-item {
            font-size: 10px;
        }
        .info-label {
            color: #666;
            margin-bottom: 2px;
            font-size: 9px;
        }
        .info-value {
            font-weight: 500;
            font-size: 10px;
        }
        .earnings-table, .deductions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 10px;
        }
        .earnings-table td, .deductions-table td {
            padding: 4px 0;
            border-bottom: 1px solid #eee;
        }
        .earnings-table td:last-child, .deductions-table td:last-child {
            text-align: right;
            font-weight: 500;
        }
        .deduction-row {
            color: #d32f2f;
        }
        .net-pay {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 2px solid #333;
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: bold;
        }
        .net-pay-value {
            color: #2e7d32;
        }
        .employer-costs {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
        .employer-costs-title {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 10px;
        }
        .payment-details {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 10px;
        }
        .payment-details-title {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 10px;
        }
        .payment-details div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .status-badge {
            margin-top: 10px;
            text-align: right;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-succeeded {
            background-color: #c8e6c9;
            color: #2e7d32;
        }
        .status-failed {
            background-color: #ffcdd2;
            color: #c62828;
        }
        .status-pending {
            background-color: #fff9c4;
            color: #f57f17;
        }
        .percentage {
            font-size: 9px;
            color: #999;
            margin-left: 5px;
        }
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 8px;
        }
        .left-column, .right-column {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h1>{{ $business->name }}</h1>
            @if($business->street_address || $business->city)
                <p>{{ $business->street_address }}{{ $business->street_address && $business->city ? ', ' : '' }}{{ $business->city }}{{ ($business->city || $business->street_address) && $business->province ? ', ' : '' }}{{ $business->province }}{{ $business->postal_code ? ' ' . $business->postal_code : '' }}</p>
            @endif
            @if($business->email || $business->phone)
                <p>{{ $business->email }}{{ $business->email && $business->phone ? ' | ' : '' }}{{ $business->phone }}</p>
            @endif
        </div>
        <div class="payslip-title">
            <h2>PAYSLIP</h2>
            <p>{{ $job->pay_period_start ? $job->pay_period_start->format('M d') : 'N/A' }} - {{ $job->pay_period_end ? $job->pay_period_end->format('M d, Y') : 'N/A' }}</p>
            <p>Paid: {{ $job->processed_at ? $job->processed_at->format('M d, Y') : 'N/A' }}</p>
        </div>
    </div>

    <div class="two-column">
            <div class="left-column">
                <div class="section">
                    <div class="section-title">Employee</div>
                    <div style="font-size: 10px;">
                        <div style="margin-bottom: 4px;"><strong>{{ $employee->name }}</strong></div>
                        @if($employee->email)
                            <div style="color: #666; margin-bottom: 3px;">{{ $employee->email }}</div>
                        @endif
                        @if($employee->id_number)
                            <div style="color: #666; margin-bottom: 3px;">ID: {{ $employee->id_number }}</div>
                        @endif
                        @if($employee->tax_number)
                            <div style="color: #666; margin-bottom: 3px;">Tax: {{ $employee->tax_number }}</div>
                        @endif
                        @if($employee->department)
                            <div style="color: #666; margin-bottom: 3px;">Dept: {{ $employee->department }}</div>
                        @endif
                        @if($employee->employment_type)
                            <div style="color: #666;">{{ ucfirst(str_replace('_', ' ', $employee->employment_type)) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        <div class="right-column">
            <div class="section">
                <div class="section-title">Earnings & Deductions</div>
                <table class="earnings-table" style="margin-bottom: 6px;">
                    <tr>
                        <td>Gross Salary</td>
                        <td>ZAR {{ number_format($job->gross_salary, 2, '.', ',') }}</td>
                    </tr>
                </table>
                <table class="deductions-table">
                    <tr class="deduction-row">
                        <td>PAYE</td>
                        <td>
                            - ZAR {{ number_format($job->paye_amount, 2, '.', ',') }}
                            @if($job->gross_salary > 0)
                                <span class="percentage">({{ number_format(($job->paye_amount / $job->gross_salary) * 100, 2) }}%)</span>
                            @endif
                        </td>
                    </tr>
                    <tr class="deduction-row">
                        <td>UIF</td>
                        <td>
                            - ZAR {{ number_format($job->uif_amount, 2, '.', ',') }}
                            @if($job->gross_salary > 0)
                                <span class="percentage">({{ number_format(($job->uif_amount / $job->gross_salary) * 100, 2) }}%)</span>
                            @endif
                        </td>
                    </tr>
                    @if($custom_deductions && count($custom_deductions) > 0)
                        @foreach($custom_deductions as $deduction)
                        <tr class="deduction-row">
                            <td>{{ is_array($deduction) ? $deduction['name'] : $deduction->name }}</td>
                            <td>
                                - ZAR {{ number_format(is_array($deduction) ? $deduction['amount'] : $deduction->amount, 2, '.', ',') }}
                                @php
                                    $deductionType = is_array($deduction) ? $deduction['type'] : $deduction->type;
                                    $deductionAmount = is_array($deduction) ? $deduction['amount'] : $deduction->amount;
                                    $originalAmount = is_array($deduction) ? ($deduction['original_amount'] ?? $deduction['amount']) : ($deduction->original_amount ?? $deduction->amount);
                                @endphp
                                @if($deductionType === 'percentage')
                                    <span class="percentage">({{ number_format($originalAmount, 2) }}%)</span>
                                @elseif($job->gross_salary > 0)
                                    <span class="percentage">({{ number_format(($deductionAmount / $job->gross_salary) * 100, 2) }}%)</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="net-pay">
        <span>Net Pay</span>
        <span class="net-pay-value">ZAR {{ number_format($job->net_salary, 2, '.', ',') }}</span>
    </div>

    @if($job->sdl_amount > 0 || $job->transaction_id)
    <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 10px;">
        @if($job->sdl_amount > 0)
        <div style="margin-bottom: 5px;">
            <strong style="color: #666;">Employer Cost:</strong> SDL - ZAR {{ number_format($job->sdl_amount, 2, '.', ',') }}
        </div>
        @endif
        @if($job->transaction_id)
        <div style="color: #666;">
            <strong>Transaction:</strong> <span style="font-family: monospace; font-size: 9px;">{{ $job->transaction_id }}</span>
        </div>
        @endif
    </div>
    @endif

    <div class="status-badge">
        <span class="status status-{{ $job->status }}">{{ strtoupper($job->status) }}</span>
    </div>
</body>
</html>
