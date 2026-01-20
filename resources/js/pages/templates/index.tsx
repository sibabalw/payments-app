import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Mail, 
    FileText, 
    CheckCircle2, 
    Palette,
    CreditCard,
    AlertTriangle,
    Bell,
    DollarSign,
    Receipt,
    Building2,
    FileSpreadsheet,
    Pencil
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Templates', href: '/templates' },
];

interface Template {
    type: string;
    name: string;
    preset: string;
    is_customized: boolean;
    is_active: boolean;
    template_id: number | null;
}

interface Props {
    templates: Record<string, Template>;
    presets: Record<string, string>;
    business?: {
        id: number;
        name: string;
    };
    error?: string;
}

const templateIcons: Record<string, typeof Mail> = {
    email_payment_success: CreditCard,
    email_payment_failed: AlertTriangle,
    email_payment_reminder: Bell,
    email_payroll_success: DollarSign,
    email_payroll_failed: AlertTriangle,
    email_payslip: Receipt,
    email_business_created: Building2,
    payslip_pdf: FileSpreadsheet,
};

const templateCategories = {
    'Email Templates': [
        'email_payment_success',
        'email_payment_failed',
        'email_payment_reminder',
        'email_payroll_success',
        'email_payroll_failed',
        'email_payslip',
        'email_business_created',
    ],
    'Document Templates': [
        'payslip_pdf',
    ],
};

export default function TemplatesIndex({ templates, presets, business, error }: Props) {
    if (error || !business) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Templates" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold">Templates</h1>
                    </div>
                    <Card>
                        <CardContent className="py-10 text-center">
                            <Palette className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-muted-foreground">{error || 'Please select a business first.'}</p>
                            <Link href="/businesses" className="mt-4 inline-block">
                                <Button variant="outline">Go to Businesses</Button>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    const getPresetBadge = (preset: string) => {
        const presetColors: Record<string, string> = {
            default: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            modern: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            minimal: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };

        return (
            <Badge className={presetColors[preset] || presetColors.default}>
                {presets[preset] || preset}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Templates" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Templates</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Customize email and document templates for {business.name}
                        </p>
                    </div>
                </div>

                {Object.entries(templateCategories).map(([category, types]) => (
                    <div key={category} className="space-y-4">
                        <h2 className="text-lg font-semibold flex items-center gap-2">
                            {category.includes('Email') ? (
                                <Mail className="h-5 w-5 text-muted-foreground" />
                            ) : (
                                <FileText className="h-5 w-5 text-muted-foreground" />
                            )}
                            {category}
                        </h2>
                        
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {types.map((type) => {
                                const template = templates[type];
                                if (!template) return null;
                                
                                const Icon = templateIcons[type] || Mail;
                                
                                return (
                                    <Card key={type} className="relative overflow-hidden">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                        <Icon className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <CardTitle className="text-sm font-medium leading-none">
                                                            {template.name}
                                                        </CardTitle>
                                                        <CardDescription className="text-xs">
                                                            {getPresetBadge(template.preset)}
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-0">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    {template.is_customized ? (
                                                        <Badge variant="outline" className="text-xs bg-green-50 text-green-700 border-green-200 dark:bg-green-950 dark:text-green-300 dark:border-green-800">
                                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                                            Customized
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="text-xs">
                                                            Default
                                                        </Badge>
                                                    )}
                                                </div>
                                                <Link href={`/templates/${type}`}>
                                                    <Button variant="ghost" size="sm">
                                                        <Pencil className="mr-1 h-4 w-4" />
                                                        Edit
                                                    </Button>
                                                </Link>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                ))}

                <Card className="mt-4 bg-muted/30">
                    <CardContent className="py-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 flex-shrink-0">
                                <Palette className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h3 className="font-medium mb-1">About Templates</h3>
                                <p className="text-sm text-muted-foreground">
                                    Templates control how your emails and documents look. You can customize colors, 
                                    text, and layout using our drag-and-drop editor. Choose from preset styles 
                                    (Default, Modern, Minimal) or create your own design. Changes apply to all 
                                    future emails sent from your business.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
