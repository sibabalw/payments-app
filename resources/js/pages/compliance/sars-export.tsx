import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    Shield,
    Upload,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Compliance', href: '/compliance' },
    { title: 'SARS Export', href: '/compliance/sars-export' },
];

interface Submission {
    id: number;
    type: string;
    type_display: string;
    period: string;
    status: string;
    created_at: string;
}

interface SARSExportProps {
    business: {
        id: number;
        name: string;
    } | null;
    submissions: Submission[];
}

export default function SARSExport({ business, submissions }: SARSExportProps) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'submitted':
                return <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Submitted to SARS</Badge>;
            case 'generated':
                return <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Ready for Export</Badge>;
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const getDownloadUrl = (submission: Submission) => {
        switch (submission.type) {
            case 'ui19':
                return `/compliance/uif/${submission.id}/download`;
            case 'emp201':
                return `/compliance/emp201/${submission.id}/download`;
            case 'irp5':
                return `/compliance/irp5/${submission.id}/download`;
            default:
                return '#';
        }
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'ui19':
                return <FileText className="h-4 w-4 text-blue-500" />;
            case 'emp201':
                return <FileSpreadsheet className="h-4 w-4 text-green-500" />;
            case 'irp5':
                return <FileText className="h-4 w-4 text-purple-500" />;
            default:
                return <FileText className="h-4 w-4" />;
        }
    };

    const handleMarkSubmitted = (submissionId: number) => {
        router.post(`/compliance/${submissionId}/mark-submitted`);
    };

    if (!business) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="SARS Export" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Shield className="h-12 w-12 text-muted-foreground mb-4" />
                            <h2 className="text-xl font-semibold mb-2">No Business Selected</h2>
                            <p className="text-muted-foreground text-center mb-4">
                                Please select a business to export SARS files.
                            </p>
                            <Button asChild>
                                <Link href="/businesses">Go to Businesses</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    const ui19Submissions = submissions.filter(s => s.type === 'ui19');
    const emp201Submissions = submissions.filter(s => s.type === 'emp201');
    const irp5Submissions = submissions.filter(s => s.type === 'irp5');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SARS Export" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/compliance">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">SARS eFiling Export</h1>
                            <p className="text-muted-foreground">
                                Download compliance files for SARS eFiling submission
                            </p>
                        </div>
                    </div>
                </div>

                {/* Quick Links */}
                <Card className="bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-950 dark:to-blue-950 border-green-200 dark:border-green-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ExternalLink className="h-5 w-5" />
                            SARS eFiling Portal
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground mb-4">
                            After downloading your compliance files, upload them to the SARS eFiling portal.
                        </p>
                        <Button variant="outline" asChild>
                            <a href="https://www.sarsefiling.co.za" target="_blank" rel="noopener noreferrer">
                                Open SARS eFiling
                                <ExternalLink className="ml-2 h-4 w-4" />
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                {/* Export Instructions */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileSpreadsheet className="h-5 w-5 text-green-500" />
                                EMP201 Files
                            </CardTitle>
                            <CardDescription>Monthly employer declaration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="text-sm space-y-2 list-decimal list-inside text-muted-foreground">
                                <li>Download the CSV file</li>
                                <li>Log in to SARS eFiling</li>
                                <li>Navigate to PAYE &gt; EMP201</li>
                                <li>Use the data to complete the form</li>
                                <li>Submit before the 7th of next month</li>
                            </ol>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileText className="h-5 w-5 text-blue-500" />
                                UIF UI-19 Files
                            </CardTitle>
                            <CardDescription>Monthly UIF declaration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="text-sm space-y-2 list-decimal list-inside text-muted-foreground">
                                <li>Download the CSV file</li>
                                <li>Visit uFiling portal</li>
                                <li>Upload the declaration</li>
                                <li>Verify the totals</li>
                                <li>Submit before the 7th</li>
                            </ol>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileText className="h-5 w-5 text-purple-500" />
                                IRP5 Certificates
                            </CardTitle>
                            <CardDescription>Annual tax certificates</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="text-sm space-y-2 list-decimal list-inside text-muted-foreground">
                                <li>Generate certificates for all employees</li>
                                <li>Distribute to employees</li>
                                <li>Submit via EMP501 reconciliation</li>
                                <li>Keep copies for 5 years</li>
                                <li>Deadline: 31 May annually</li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                {/* All Submissions */}
                {submissions.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Download className="h-5 w-5" />
                                Available Exports ({submissions.length})
                            </CardTitle>
                            <CardDescription>
                                Download files ready for SARS submission
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="text-left p-3">Type</th>
                                            <th className="text-left p-3">Period</th>
                                            <th className="text-left p-3">Status</th>
                                            <th className="text-left p-3">Generated</th>
                                            <th className="text-right p-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {submissions.map((submission) => (
                                            <tr key={submission.id} className="border-b hover:bg-muted/30">
                                                <td className="p-3">
                                                    <div className="flex items-center gap-2">
                                                        {getTypeIcon(submission.type)}
                                                        <span className="font-medium">{submission.type_display}</span>
                                                    </div>
                                                </td>
                                                <td className="p-3">{submission.period}</td>
                                                <td className="p-3">{getStatusBadge(submission.status)}</td>
                                                <td className="p-3 text-muted-foreground">
                                                    {formatDate(submission.created_at)}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button variant="outline" size="sm" asChild>
                                                            <a href={getDownloadUrl(submission)}>
                                                                <Download className="mr-2 h-4 w-4" />
                                                                Download
                                                            </a>
                                                        </Button>
                                                        {submission.status === 'generated' && (
                                                            <Button 
                                                                variant="default" 
                                                                size="sm"
                                                                onClick={() => handleMarkSubmitted(submission.id)}
                                                            >
                                                                <CheckCircle className="mr-2 h-4 w-4" />
                                                                Mark Submitted
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Shield className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No Exports Available</h3>
                            <p className="text-muted-foreground text-center mb-4">
                                Generate compliance data first before exporting for SARS.
                            </p>
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/compliance/emp201">EMP201</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/compliance/uif">UIF</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/compliance/irp5">IRP5</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Important Notice */}
                <Card className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                            <Shield className="h-5 w-5" />
                            Important Notice
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-amber-800 dark:text-amber-200 text-sm">
                        <ul className="space-y-2">
                            <li>
                                <strong>EMP201 Deadline:</strong> Submit by the 7th of the month following the payroll period.
                            </li>
                            <li>
                                <strong>UIF UI-19 Deadline:</strong> Submit by the 7th of the month following the contribution period.
                            </li>
                            <li>
                                <strong>IRP5/IT3(a) Deadline:</strong> Submit via EMP501 by 31 May each year.
                            </li>
                            <li>
                                <strong>Penalties:</strong> Late submissions may incur penalties and interest from SARS.
                            </li>
                            <li>
                                <strong>Record Keeping:</strong> Keep all compliance records for a minimum of 5 years.
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
