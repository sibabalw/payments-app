import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ToggleLeft,
    ToggleRight,
    Settings,
    AlertCircle,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Feature Flags', href: '/admin/feature-flags' },
];

interface FeatureFlagsProps {
    featureFlags: {
        redis: {
            enabled: boolean;
            locks: boolean;
            idempotency: boolean;
            queues: boolean;
        };
    };
    currentState: {
        redis: {
            enabled: boolean;
            locks: boolean;
            idempotency: boolean;
            queues: boolean;
        };
    };
    usageStats: {
        redis: {
            enabled: {
                locks_used: number;
                idempotency_used: number;
                queues_used: number;
            };
        };
    };
}

export default function FeatureFlags({
    featureFlags,
    currentState,
    usageStats,
}: FeatureFlagsProps) {
    const [toggleDialogOpen, setToggleDialogOpen] = useState(false);
    const [selectedFeature, setSelectedFeature] = useState<{
        category: string;
        feature: string;
        enabled: boolean;
    } | null>(null);

    const { post, processing } = useForm({});

    const handleToggle = (category: string, feature: string, currentEnabled: boolean) => {
        setSelectedFeature({
            category,
            feature,
            enabled: !currentEnabled,
        });
        setToggleDialogOpen(true);
    };

    const confirmToggle = () => {
        if (!selectedFeature) {
            return;
        }

        post(
            '/admin/feature-flags/toggle',
            {
                category: selectedFeature.category,
                feature: selectedFeature.feature,
                enabled: selectedFeature.enabled,
            },
            {
                onSuccess: () => {
                    setToggleDialogOpen(false);
                    setSelectedFeature(null);
                },
            }
        );
    };

    const getFeatureDescription = (category: string, feature: string): string => {
        const descriptions: Record<string, Record<string, string>> = {
            redis: {
                enabled: 'Enable Redis for caching and performance',
                locks: 'Use Redis for distributed locking',
                idempotency: 'Use Redis for idempotency keys',
                queues: 'Use Redis for queue processing',
            },
        };

        return descriptions[category]?.[feature] || 'Feature flag';
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Feature Flags" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Feature Flags</h1>
                        <p className="text-sm text-muted-foreground">Manage application feature flags</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {/* Info Alert */}
                <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-900 dark:bg-yellow-950">
                    <CardContent className="pt-6">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5" />
                            <div>
                                <p className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    Environment Variable Configuration
                                </p>
                                <p className="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                    Feature flags are controlled via environment variables. Toggling here will log the
                                    action, but you'll need to update your .env file and restart the application for
                                    changes to take effect.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Redis Features */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="h-5 w-5" />
                            Redis Features
                        </CardTitle>
                        <CardDescription>
                            Redis is a drop-in accelerator. When disabled, the database is used as the default backend.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {Object.entries(featureFlags.redis).map(([key, defaultValue]) => {
                                const isEnabled = currentState.redis[key as keyof typeof currentState.redis];
                                const envKey = `REDIS_${key.toUpperCase()}_ENABLED`;

                                return (
                                    <div
                                        key={key}
                                        className="flex items-center justify-between border rounded-lg p-4"
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-medium capitalize">
                                                    {key === 'enabled' ? 'Redis' : key.replace('_', ' ')}
                                                </span>
                                                <Badge variant={isEnabled ? 'default' : 'secondary'}>
                                                    {isEnabled ? 'Enabled' : 'Disabled'}
                                                </Badge>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {getFeatureDescription('redis', key)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1 font-mono">
                                                {envKey} = {isEnabled ? 'true' : 'false'}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleToggle('redis', key, isEnabled)}
                                            disabled={processing}
                                        >
                                            {isEnabled ? (
                                                <>
                                                    <ToggleRight className="mr-2 h-4 w-4" />
                                                    Disable
                                                </>
                                            ) : (
                                                <>
                                                    <ToggleLeft className="mr-2 h-4 w-4" />
                                                    Enable
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                <ConfirmationDialog
                    open={toggleDialogOpen}
                    onOpenChange={setToggleDialogOpen}
                    onConfirm={confirmToggle}
                    title={
                        selectedFeature
                            ? `${selectedFeature.enabled ? 'Enable' : 'Disable'} ${selectedFeature.feature}`
                            : 'Toggle Feature Flag'
                    }
                    description={
                        selectedFeature
                            ? `This will ${selectedFeature.enabled ? 'enable' : 'disable'} the ${selectedFeature.feature} feature flag. You will need to update the environment variable ${selectedFeature.category.toUpperCase()}_${selectedFeature.feature.toUpperCase()}_ENABLED and restart the application for changes to take effect.`
                            : ''
                    }
                    confirmText={selectedFeature?.enabled ? 'Enable' : 'Disable'}
                    variant="default"
                />
            </div>
        </AdminLayout>
    );
}
