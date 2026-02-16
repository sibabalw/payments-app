<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>IRP5 Tax Certificate - {{ $data['employee']['name'] ?? 'Employee' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14px;
            font-weight: normal;
            color: #666;
        }
        .certificate-number {
            text-align: right;
            font-size: 9px;
            color: #666;
            margin-bottom: 15px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-left: 4px solid #333;
        }
        .two-columns {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .field {
            margin-bottom: 8px;
        }
        .field-label {
            font-weight: bold;
            color: #666;
            font-size: 9px;
            text-transform: uppercase;
        }
        .field-value {
            font-size: 11px;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table th {
            background-color: #f5f5f5;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            font-size: 9px;
        }
        table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        table td.code {
            width: 80px;
            text-align: center;
            font-family: monospace;
        }
        table td.amount {
            width: 120px;
            text-align: right;
            font-family: monospace;
        }
        .summary-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 20px;
        }
        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .summary-label {
            display: table-cell;
            width: 70%;
            font-weight: bold;
        }
        .summary-value {
            display: table-cell;
            width: 30%;
            text-align: right;
            font-family: monospace;
            font-size: 11px;
        }
        .total-row {
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 12px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #666;
        }
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 45%;
            padding-top: 30px;
            border-top: 1px solid #333;
        }
        .signature-label {
            font-size: 9px;
            color: #666;
        }
        .tax-year-badge {
            display: inline-block;
            background-color: #333;
            color: #fff;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>EMPLOYEES TAX CERTIFICATE</h1>
        <h2>IRP5 <span class="tax-year-badge">{{ $data['tax_year'] ?? 'N/A' }}</span></h2>
    </div>

    <div class="certificate-number">
        Certificate No: {{ $data['certificate_number'] ?? 'N/A' }}<br>
        Generated: {{ $data['generated_at'] ?? now()->format('Y-m-d H:i:s') }}
    </div>

    <!-- Employer Details -->
    <div class="section">
        <div class="section-title">EMPLOYER DETAILS</div>
        <div class="two-columns">
            <div class="column">
                <div class="field">
                    <div class="field-label">Employer Name</div>
                    <div class="field-value">{{ $data['employer']['name'] ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Trading Name</div>
                    <div class="field-value">{{ $data['employer']['trading_name'] ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="column">
                <div class="field">
                    <div class="field-label">Registration Number</div>
                    <div class="field-value">{{ $data['employer']['registration_number'] ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">PAYE Reference Number</div>
                    <div class="field-value">{{ $data['employer']['paye_reference'] ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
        <div class="field">
            <div class="field-label">Address</div>
            <div class="field-value">{{ $data['employer']['address'] ?? 'N/A' }}</div>
        </div>
    </div>

    <!-- Employee Details -->
    <div class="section">
        <div class="section-title">EMPLOYEE DETAILS</div>
        <div class="two-columns">
            <div class="column">
                <div class="field">
                    <div class="field-label">Employee Name</div>
                    <div class="field-value">{{ $data['employee']['name'] ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">ID Number</div>
                    <div class="field-value">{{ $data['employee']['id_number'] ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Tax Reference Number</div>
                    <div class="field-value">{{ $data['employee']['tax_number'] ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="column">
                <div class="field">
                    <div class="field-label">Employment Period</div>
                    <div class="field-value">
                        {{ $data['employee']['employment_start'] ?? 'N/A' }} to 
                        {{ $data['employee']['employment_end'] ?? 'N/A' }}
                    </div>
                </div>
                <div class="field">
                    <div class="field-label">Pay Periods</div>
                    <div class="field-value">{{ $data['summary']['periods_paid'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Income Sources -->
    <div class="section">
        <div class="section-title">INCOME (Source Codes 3000 - 3999)</div>
        <table>
            <thead>
                <tr>
                    <th>Source Code</th>
                    <th>Description</th>
                    <th style="text-align: right;">Amount (ZAR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['income']['sources'] ?? [] as $source)
                <tr>
                    <td class="code">{{ $source['code'] ?? 'N/A' }}</td>
                    <td>{{ $source['description'] ?? 'N/A' }}</td>
                    <td class="amount">R {{ number_format($source['amount'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
                <tr style="font-weight: bold; background-color: #f5f5f5;">
                    <td colspan="2">TOTAL INCOME</td>
                    <td class="amount">R {{ number_format($data['income']['total'] ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Deductions -->
    <div class="section">
        <div class="section-title">DEDUCTIONS (Source Codes 4000 - 4999)</div>
        <table>
            <thead>
                <tr>
                    <th>Source Code</th>
                    <th>Description</th>
                    <th style="text-align: right;">Amount (ZAR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['deductions']['items'] ?? [] as $deduction)
                <tr>
                    <td class="code">{{ $deduction['code'] ?? 'N/A' }}</td>
                    <td>{{ $deduction['description'] ?? 'N/A' }}</td>
                    <td class="amount">R {{ number_format($deduction['amount'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
                <tr style="font-weight: bold; background-color: #f5f5f5;">
                    <td colspan="2">TOTAL DEDUCTIONS</td>
                    <td class="amount">R {{ number_format($data['deductions']['total'] ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Summary -->
    <div class="summary-box">
        <div class="section-title" style="margin: -15px -15px 15px -15px; padding: 10px 15px;">CERTIFICATE SUMMARY</div>
        <div class="summary-row">
            <div class="summary-label">Gross Remuneration (Code 3601)</div>
            <div class="summary-value">R {{ number_format($data['summary']['gross_remuneration'] ?? 0, 2) }}</div>
        </div>
        <div class="summary-row">
            <div class="summary-label">Total Deductions</div>
            <div class="summary-value">R {{ number_format($data['summary']['total_deductions'] ?? 0, 2) }}</div>
        </div>
        <div class="summary-row total-row">
            <div class="summary-label">PAYE Deducted (Code 4102)</div>
            <div class="summary-value">R {{ number_format($data['summary']['paye_deducted'] ?? 0, 2) }}</div>
        </div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Authorized Signature (Employer)</div>
        </div>
        <div style="display: table-cell; width: 10%;"></div>
        <div class="signature-box">
            <div class="signature-label">Date</div>
        </div>
    </div>

    <div class="footer">
        <p><strong>Important Notice:</strong> This certificate must be used by the employee when completing their annual income tax return (ITR12). 
        The employer is required to submit the IRP5 data to SARS electronically via the EMP501 reconciliation process.</p>
        <p style="margin-top: 10px;">This document was generated electronically and is valid without a signature for information purposes. 
        The original signed copy should be retained for a minimum period of 5 years.</p>
    </div>
</body>
</html>
