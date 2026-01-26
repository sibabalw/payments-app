<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ucwords(str_replace('_', ' ', $reportType)) }} Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 9px;
            color: #666;
            margin: 2px 0;
        }
        .summary {
            margin-bottom: 15px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .summary-card {
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 4px;
        }
        .summary-card-title {
            font-size: 8px;
            color: #666;
            margin-bottom: 3px;
        }
        .summary-card-value {
            font-size: 14px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }
        th {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 5px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 15px 0 8px 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ ucwords(str_replace('_', ' ', $reportType)) }} Report</h1>
        @if($business)
            <p><strong>Business:</strong> {{ $business->name }}</p>
        @endif
        @if($startDate || $endDate)
            <p><strong>Period:</strong> 
                {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : 'All time' }} - 
                {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('M d, Y') : 'All time' }}
            </p>
        @endif
        <p><strong>Generated:</strong> {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

    @if($reportType === 'payroll_summary')
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-card-title">Total Jobs</div>
                <div class="summary-card-value">{{ $report['total_jobs'] ?? 0 }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Gross</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_gross'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Deductions</div>
                <div class="summary-card-value">ZAR {{ number_format(($report['total_paye'] ?? 0) + ($report['total_uif'] ?? 0) + ($report['total_adjustments'] ?? 0), 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Net</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_net'] ?? 0, 2, '.', ',') }}</div>
            </div>
        </div>

        @if(isset($report['jobs']) && count($report['jobs']) > 0)
            <div class="section-title">Payroll Jobs</div>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">PAYE</th>
                        <th class="text-right">UIF</th>
                        <th class="text-right">Adjustments</th>
                        <th class="text-right">Net</th>
                        <th>Period</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['jobs'] as $job)
                    <tr>
                        <td>{{ $job['employee_name'] ?? 'N/A' }}</td>
                        <td class="text-right">ZAR {{ number_format($job['gross_salary'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($job['paye_amount'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($job['uif_amount'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($job['adjustments_total'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($job['net_salary'] ?? 0, 2, '.', ',') }}</td>
                        <td>{{ $job['pay_period_start'] ?? 'N/A' }} - {{ $job['pay_period_end'] ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @elseif($reportType === 'payroll_by_employee')
        @if(isset($report['employees']) && count($report['employees']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-right">Payments</th>
                        <th class="text-right">Total Gross</th>
                        <th class="text-right">Total PAYE</th>
                        <th class="text-right">Total UIF</th>
                        <th class="text-right">Total Custom</th>
                        <th class="text-right">Total Net</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['employees'] as $emp)
                    <tr>
                        <td>{{ $emp['employee_name'] ?? 'N/A' }}</td>
                        <td class="text-right">{{ $emp['total_jobs'] ?? 0 }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_gross'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_paye'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_uif'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_adjustments'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_net'] ?? 0, 2, '.', ',') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @elseif($reportType === 'tax_summary')
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-card-title">PAYE Total</div>
                <div class="summary-card-value">ZAR {{ number_format($report['paye']['total'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">UIF Total</div>
                <div class="summary-card-value">ZAR {{ number_format($report['uif']['total'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">SDL Total</div>
                <div class="summary-card-value">ZAR {{ number_format($report['sdl']['total'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Tax Liability</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_tax_liability'] ?? 0, 2, '.', ',') }}</div>
            </div>
        </div>

    @elseif($reportType === 'deductions_summary')
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-card-title">Statutory</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_statutory_deductions'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Adjustments</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_adjustments'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_all_deductions'] ?? 0, 2, '.', ',') }}</div>
            </div>
        </div>

        @if(isset($report['deductions']) && count($report['deductions']) > 0)
            <div class="section-title">Adjustments Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-right">Total Amount</th>
                        <th class="text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['deductions'] as $deduction)
                    <tr>
                        <td>{{ $deduction['name'] ?? 'Unknown' }}</td>
                        <td>{{ ucfirst($deduction['type'] ?? 'fixed') }}</td>
                        <td class="text-right">ZAR {{ number_format($deduction['total_amount'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">{{ $deduction['count'] ?? 0 }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @elseif($reportType === 'payment_summary')
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-card-title">Total Payments</div>
                <div class="summary-card-value">{{ $report['total_jobs'] ?? 0 }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Amount</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_amount'] ?? 0, 2, '.', ',') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Fees</div>
                <div class="summary-card-value">ZAR {{ number_format($report['total_fees'] ?? 0, 2, '.', ',') }}</div>
            </div>
        </div>

        @if(isset($report['jobs']) && count($report['jobs']) > 0)
            <div class="section-title">Payment Jobs</div>
            <table>
                <thead>
                    <tr>
                        <th>Receiver</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Fee</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['jobs'] as $job)
                    <tr>
                        <td>{{ $job['receiver_name'] ?? 'N/A' }}</td>
                        <td class="text-right">ZAR {{ number_format($job['amount'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($job['fee'] ?? 0, 2, '.', ',') }}</td>
                        <td>{{ $job['processed_at'] ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @elseif($reportType === 'employee_earnings')
        @if(isset($report['employees']) && count($report['employees']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-right">Payments</th>
                        <th class="text-right">Total Gross</th>
                        <th class="text-right">Avg Gross</th>
                        <th class="text-right">Total Net</th>
                        <th class="text-right">Avg Net</th>
                        <th class="text-right">Deductions</th>
                        <th class="text-right">Deduction %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['employees'] as $emp)
                    <tr>
                        <td>{{ $emp['employee_name'] ?? 'N/A' }}</td>
                        <td class="text-right">{{ $emp['total_payments'] ?? 0 }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_gross'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['average_gross'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_net'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['average_net'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">ZAR {{ number_format($emp['total_deductions'] ?? 0, 2, '.', ',') }}</td>
                        <td class="text-right">{{ number_format($emp['deduction_percentage'] ?? 0, 2) }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
</body>
</html>
