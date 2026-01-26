import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download, FileText, TrendingUp, Users, Receipt, DollarSign, FileSpreadsheet, Loader2, Mail } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
];

export default function ReportsIndex({ report: initialReport, report_type, business_id, start_date, end_date, businesses }: any) {
    // Convert business_id to string, handle null/undefined
    const initialBusinessId = business_id ? String(business_id) : '';
    
    const [localReportType, setLocalReportType] = useState(report_type || 'payroll_summary');
    const [localBusinessId, setLocalBusinessId] = useState(initialBusinessId);
    const [localStartDate, setLocalStartDate] = useState(start_date || '');
    const [localEndDate, setLocalEndDate] = useState(end_date || '');
    const [report, setReport] = useState(initialReport);
    const [loading, setLoading] = useState(!initialReport); // Show loading if no initial report
    const [loadingButtons, setLoadingButtons] = useState<Record<string, boolean>>({});
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const sseRef = useRef<EventSource | null>(null);

    // Fetch report data when filters change
    const fetchReportData = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {
                report_type: localReportType,
            };
            
            if (localBusinessId) {
                params.business_id = localBusinessId;
            }
            if (localStartDate) {
                params.start_date = localStartDate;
            }
            if (localEndDate) {
                params.end_date = localEndDate;
            }

            // Use fetch with proper headers for JSON API endpoint
            const response = await fetch(`/reports/data?${new URLSearchParams(params).toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            if (response.ok) {
                const data = await response.json();
                setReport(data);
            } else {
                const errorText = await response.text();
                console.error('Failed to fetch report data:', response.status, response.statusText, errorText);
                setReport(null);
            }
        } catch (error: any) {
            console.error('Error fetching report data:', error);
            setReport(null);
        } finally {
            setLoading(false);
        }
    };

    // Fetch report data on mount and when filters change
    useEffect(() => {
        fetchReportData();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localReportType, localBusinessId, localStartDate, localEndDate]);

    const handleFilterChange = () => {
        router.get('/reports', {
            report_type: localReportType,
            business_id: localBusinessId || null,
            start_date: localStartDate || null,
            end_date: localEndDate || null,
        }, { preserveState: true });
    };

    const getExportUrl = (format: string, delivery: 'download' | 'email' = 'download') => {
        const params = new URLSearchParams({
            report_type: localReportType,
            delivery: delivery,
            ...(localBusinessId && { business_id: localBusinessId }),
            ...(localStartDate && { start_date: localStartDate }),
            ...(localEndDate && { end_date: localEndDate }),
        });
        return `/reports/export/${format}?${params.toString()}`;
    };

    const handleExportClick = async (format: string, delivery: 'download' | 'email') => {
        const buttonKey = `${format}-${delivery}`;
        setLoadingButtons(prev => ({ ...prev, [buttonKey]: true }));
        setSuccessMessage(null);
        setErrorMessage(null);

        try {
            const response = await fetch(getExportUrl(format, delivery), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const contentType = response.headers.get('content-type');
                if (!contentType?.includes('application/json')) {
                    setErrorMessage('Unexpected response. Please try again.');
                    setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
                    return;
                }
                const data = await response.json();

                if (data.success === false && data.error) {
                    setErrorMessage(data.error);
                    setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
                    return;
                }

                if (delivery === 'download') {
                    if (data.download_url && !data.sse_url) {
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        iframe.src = data.download_url;
                        document.body.appendChild(iframe);
                        setTimeout(() => {
                            if (iframe.parentNode) document.body.removeChild(iframe);
                        }, 60000);
                        setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
                        setSuccessMessage('Report downloaded successfully!');
                    } else {
                        trackDownloadProgress(data.report_generation_id, data.sse_url, data.download_url, buttonKey);
                    }
                } else {
                    setSuccessMessage(`Your ${format.toUpperCase()} report is being generated and will be sent to your email.`);
                    setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
                }
            } else {
                const errorText = await response.text();
                setErrorMessage(`Failed to start report generation: ${errorText}`);
                setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
            }
        } catch (error: any) {
            console.error('Error exporting report:', error);
            setErrorMessage('Failed to start report generation. Please try again.');
            setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
        }
    };

    const trackDownloadProgress = (reportGenerationId: number, sseUrl: string, downloadUrl: string, buttonKey: string) => {
        const clearLoading = () => {
            setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
            if (sseRef.current) {
                sseRef.current.close();
                sseRef.current = null;
            }
        };

        if (sseRef.current) {
            sseRef.current.close();
            sseRef.current = null;
        }

        const eventSource = new EventSource(sseUrl);
        sseRef.current = eventSource;

        eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                if (data.status === 'completed') {
                    eventSource.close();
                    sseRef.current = null;
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = data.download_url ?? downloadUrl;
                    document.body.appendChild(iframe);
                    setTimeout(() => {
                        if (iframe.parentNode) document.body.removeChild(iframe);
                    }, 60000);
                    setLoadingButtons(prev => ({ ...prev, [buttonKey]: false }));
                    setSuccessMessage('Report downloaded successfully!');
                } else if (data.status === 'failed') {
                    setErrorMessage(data.error_message || 'Report generation failed.');
                    clearLoading();
                }
            } catch (e) {
                console.error('Error parsing SSE data:', e);
            }
        };

        eventSource.onerror = () => {
            eventSource.close();
            if (sseRef.current === eventSource) sseRef.current = null;
            setErrorMessage('Connection lost. Please try again.');
            clearLoading();
        };
    };

    useEffect(() => {
        return () => {
            if (sseRef.current) {
                sseRef.current.close();
                sseRef.current = null;
            }
        };
    }, []);

    const formatCurrency = (amount: number | string) => {
        return `ZAR ${parseFloat(String(amount)).toLocaleString('en-ZA', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    const renderReport = () => {
        if (!report) {
            return (
                <Card>
                    <CardContent className="py-12 text-center text-muted-foreground">
                        No report data available. Please adjust your filters or wait for data to load.
                    </CardContent>
                </Card>
            );
        }

        switch (localReportType) {
            case 'payroll_summary':
                return renderPayrollSummary();
            case 'payroll_by_employee':
                return renderPayrollByEmployee();
            case 'tax_summary':
                return renderTaxSummary();
            case 'deductions_summary':
                return renderDeductionsSummary();
            case 'payment_summary':
                return renderPaymentSummary();
            case 'employee_earnings':
                return renderEmployeeEarnings();
            default:
                return renderPayrollSummary();
        }
    };

    const renderPayrollSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{report.total_jobs || 0}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Gross</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_gross || 0)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency((report.total_paye || 0) + (report.total_uif || 0) + (report.total_adjustments || 0))}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Net</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{formatCurrency(report.total_net || 0)}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span>PAYE:</span>
                                <span>{formatCurrency(report.total_paye || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>UIF:</span>
                                <span>{formatCurrency(report.total_uif || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Adjustments:</span>
                                <span>{formatCurrency(report.total_adjustments || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>SDL (Employer Cost):</span>
                                <span>{formatCurrency(report.total_sdl || 0)}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {report.jobs && report.jobs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payroll Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Employee</th>
                                            <th className="text-right p-2">Gross</th>
                                            <th className="text-right p-2">PAYE</th>
                                            <th className="text-right p-2">UIF</th>
                                            <th className="text-right p-2">Adjustments</th>
                                            <th className="text-right p-2">Net</th>
                                            <th className="text-left p-2">Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.jobs.map((job: any) => (
                                            <tr key={job.id} className="border-b">
                                                <td className="p-2">{job.employee_name}</td>
                                                <td className="text-right p-2">{formatCurrency(job.gross_salary)}</td>
                                                <td className="text-right p-2 text-red-600">{formatCurrency(job.paye_amount)}</td>
                                                <td className="text-right p-2 text-red-600">{formatCurrency(job.uif_amount)}</td>
                                                <td className="text-right p-2 text-red-600">{formatCurrency(job.adjustments_total || 0)}</td>
                                                <td className="text-right p-2 font-medium text-green-600">{formatCurrency(job.net_salary)}</td>
                                                <td className="p-2 text-xs text-muted-foreground">
                                                    {formatDate(job.pay_period_start)} - {formatDate(job.pay_period_end)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        );
    };

    const renderPayrollByEmployee = () => {
        if (!report || !report.employees) return null;
        return (
            <div className="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                            <div>
                                <div className="text-muted-foreground">Total Employees</div>
                                <div className="text-lg font-bold">{report.total_employees || 0}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Gross</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_gross || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Net</div>
                                <div className="text-lg font-bold text-green-600">{formatCurrency(report.summary?.total_net || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total PAYE</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_paye || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total UIF</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_uif || 0)}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    {report.employees.map((emp: any) => (
                        <Card key={emp.employee_id}>
                            <CardHeader>
                                <CardTitle>{emp.employee_name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Payments</div>
                                        <div className="text-lg font-bold">{emp.total_jobs}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Gross</div>
                                        <div className="text-lg font-bold">{formatCurrency(emp.total_gross)}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Net</div>
                                        <div className="text-lg font-bold text-green-600">{formatCurrency(emp.total_net)}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Deductions</div>
                                        <div className="text-lg font-bold text-red-600">
                                            {formatCurrency(emp.total_paye + emp.total_uif + (emp.total_adjustments || 0))}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        );
    };

    const renderTaxSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>PAYE</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-sm text-muted-foreground">Total</div>
                                    <div className="text-2xl font-bold">{formatCurrency(report.paye?.total || 0)}</div>
                                </div>
                                <div className="text-sm">
                                    <div className="text-muted-foreground">Count: {report.paye?.count || 0}</div>
                                    <div className="text-muted-foreground">Average: {formatCurrency(report.paye?.average || 0)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>UIF</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-sm text-muted-foreground">Total</div>
                                    <div className="text-2xl font-bold">{formatCurrency(report.uif?.total || 0)}</div>
                                </div>
                                <div className="text-sm">
                                    <div className="text-muted-foreground">Count: {report.uif?.count || 0}</div>
                                    <div className="text-muted-foreground">Average: {formatCurrency(report.uif?.average || 0)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SDL (Employer Cost)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-sm text-muted-foreground">Total</div>
                                    <div className="text-2xl font-bold">{formatCurrency(report.sdl?.total || 0)}</div>
                                </div>
                                <div className="text-sm">
                                    <div className="text-muted-foreground">Count: {report.sdl?.count || 0}</div>
                                    <div className="text-muted-foreground">Average: {formatCurrency(report.sdl?.average || 0)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span>Total Tax Liability (PAYE + UIF):</span>
                                <span className="font-bold">{formatCurrency(report.total_tax_liability || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Total Employer Costs (SDL):</span>
                                <span className="font-bold">{formatCurrency(report.total_employer_costs || 0)}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    };

    const renderDeductionsSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Statutory Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_statutory_deductions || 0)}</div>
                            <div className="text-sm text-muted-foreground mt-2">PAYE + UIF</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Custom Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_adjustments || 0)}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total All Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{formatCurrency(report.total_all_deductions || 0)}</div>
                        </CardContent>
                    </Card>
                </div>

                {report.deductions && report.deductions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Adjustments Breakdown</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Deduction Name</th>
                                            <th className="text-left p-2">Type</th>
                                            <th className="text-right p-2">Total Amount</th>
                                            <th className="text-right p-2">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.deductions.map((deduction: any, index: number) => (
                                            <tr key={index} className="border-b">
                                                <td className="p-2">{deduction.name}</td>
                                                <td className="p-2 capitalize">{deduction.type}</td>
                                                <td className="text-right p-2 font-medium">{formatCurrency(deduction.total_amount)}</td>
                                                <td className="text-right p-2">{deduction.count}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        );
    };

    const renderPaymentSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Total Payments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{report.total_jobs || 0}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total Amount</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{formatCurrency(report.total_amount || 0)}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total Fees</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_fees || 0)}</div>
                        </CardContent>
                    </Card>
                </div>

                {report.jobs && report.jobs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Receiver</th>
                                            <th className="text-right p-2">Amount</th>
                                            <th className="text-right p-2">Fee</th>
                                            <th className="text-left p-2">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.jobs.map((job: any) => (
                                            <tr key={job.id} className="border-b">
                                                <td className="p-2">{job.receiver_name}</td>
                                                <td className="text-right p-2 font-medium">{formatCurrency(job.amount)}</td>
                                                <td className="text-right p-2">{formatCurrency(job.fee)}</td>
                                                <td className="p-2 text-xs text-muted-foreground">{formatDate(job.processed_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        );
    };

    const renderEmployeeEarnings = () => {
        if (!report || !report.employees) return null;
        return (
            <div className="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div className="text-muted-foreground">Total Employees</div>
                                <div className="text-lg font-bold">{report.summary?.total_employees || 0}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Gross</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_gross || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Net</div>
                                <div className="text-lg font-bold text-green-600">{formatCurrency(report.summary?.total_net || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Deductions</div>
                                <div className="text-lg font-bold text-red-600">{formatCurrency(report.summary?.total_deductions || 0)}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="text-left p-2">Employee</th>
                                <th className="text-right p-2">Payments</th>
                                <th className="text-right p-2">Total Gross</th>
                                <th className="text-right p-2">Avg Gross</th>
                                <th className="text-right p-2">Total Net</th>
                                <th className="text-right p-2">Avg Net</th>
                                <th className="text-right p-2">Total Deductions</th>
                                <th className="text-right p-2">Deduction %</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.employees.map((emp: any) => (
                                <tr key={emp.employee_id} className="border-b">
                                    <td className="p-2">
                                        <div className="font-medium">{emp.employee_name}</div>
                                        {emp.employee_email && (
                                            <div className="text-xs text-muted-foreground">{emp.employee_email}</div>
                                        )}
                                    </td>
                                    <td className="text-right p-2">{emp.total_payments}</td>
                                    <td className="text-right p-2 font-medium">{formatCurrency(emp.total_gross)}</td>
                                    <td className="text-right p-2 text-muted-foreground">{formatCurrency(emp.average_gross)}</td>
                                    <td className="text-right p-2 font-medium text-green-600">{formatCurrency(emp.total_net)}</td>
                                    <td className="text-right p-2 text-muted-foreground">{formatCurrency(emp.average_net)}</td>
                                    <td className="text-right p-2 text-red-600">{formatCurrency(emp.total_deductions)}</td>
                                    <td className="text-right p-2">{emp.deduction_percentage.toFixed(2)}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Reports</h1>
                    <div className="flex flex-wrap gap-2">
                        {/* CSV Buttons */}
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => handleExportClick('csv', 'download')}
                            disabled={loadingButtons['csv-download']}
                        >
                            {loadingButtons['csv-download'] ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Download CSV
                                </>
                            )}
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => handleExportClick('csv', 'email')}
                            disabled={loadingButtons['csv-email']}
                        >
                            {loadingButtons['csv-email'] ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Sending...
                                </>
                            ) : (
                                <>
                                    <Mail className="mr-2 h-4 w-4" />
                                    Email CSV
                                </>
                            )}
                        </Button>
                        
                        {/* Excel Buttons */}
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => handleExportClick('excel', 'download')}
                            disabled={loadingButtons['excel-download']}
                        >
                            {loadingButtons['excel-download'] ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Download Excel
                                </>
                            )}
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => handleExportClick('excel', 'email')}
                            disabled={loadingButtons['excel-email']}
                        >
                            {loadingButtons['excel-email'] ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Sending...
                                </>
                            ) : (
                                <>
                                    <Mail className="mr-2 h-4 w-4" />
                                    Email Excel
                                </>
                            )}
                        </Button>
                        
                        {/* PDF Buttons */}
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => handleExportClick('pdf', 'download')}
                            disabled={loadingButtons['pdf-download']}
                        >
                            {loadingButtons['pdf-download'] ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <FileText className="mr-2 h-4 w-4" />
                                    Download PDF
                                </>
                            )}
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => handleExportClick('pdf', 'email')}
                            disabled={loadingButtons['pdf-email']}
                        >
                            {loadingButtons['pdf-email'] ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Sending...
                                </>
                            ) : (
                                <>
                                    <Mail className="mr-2 h-4 w-4" />
                                    Email PDF
                                </>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Success/Error Messages */}
                {successMessage && (
                    <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400">
                        {successMessage}
                    </div>
                )}
                {errorMessage && (
                    <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        {errorMessage}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <Label htmlFor="report_type">Report Type</Label>
                                <Select
                                    value={localReportType}
                                    onValueChange={(value) => {
                                        setLocalReportType(value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="payroll_summary">Payroll Summary</SelectItem>
                                        <SelectItem value="payroll_by_employee">Payroll by Employee</SelectItem>
                                        <SelectItem value="tax_summary">Tax Summary</SelectItem>
                                        <SelectItem value="deductions_summary">Deductions Summary</SelectItem>
                                        <SelectItem value="payment_summary">Payment Summary</SelectItem>
                                        <SelectItem value="employee_earnings">Employee Earnings</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="business_id">Business</Label>
                                <Select
                                    value={localBusinessId || 'all'}
                                    onValueChange={(value) => {
                                        setLocalBusinessId(value === 'all' ? '' : value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="All businesses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All businesses</SelectItem>
                                        {businesses.map((business: any) => (
                                            <SelectItem key={business.id} value={String(business.id)}>
                                                {business.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={localStartDate}
                                    onChange={(e) => {
                                        setLocalStartDate(e.target.value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                />
                            </div>

                            <div>
                                <Label htmlFor="end_date">End Date</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={localEndDate}
                                    onChange={(e) => {
                                        setLocalEndDate(e.target.value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {loading ? (
                    <Card>
                        <CardContent className="flex items-center justify-center py-12">
                            <div className="flex flex-col items-center gap-4">
                                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                <p className="text-sm text-muted-foreground">Loading report data...</p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    renderReport()
                )}
            </div>
        </AppLayout>
    );
}
