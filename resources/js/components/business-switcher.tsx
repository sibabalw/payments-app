import { useState } from 'react';
import { Building2, CheckCircle2, ChevronDown, Loader2, Plus, AlertCircle, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Badge } from '@/components/ui/badge';
import { router, usePage, Link } from '@inertiajs/react';
import { type SharedData } from '@/types';

// Helper function to get business initials
const getBusinessInitials = (name: string): string => {
    if (!name) return '?';
    
    const words = name.trim().split(/\s+/);
    if (words.length === 1) {
        // Single word: take first 2 letters
        return name.substring(0, 2).toUpperCase();
    }
    // Multiple words: take first letter of first two words
    return (words[0][0] + words[1][0]).toUpperCase();
};

export function BusinessSwitcher() {
    const { currentBusiness, userBusinesses = [] } = usePage<SharedData>().props;
    const [isOpen, setIsOpen] = useState(false);
    const [switching, setSwitching] = useState<number | null>(null);

    const handleSwitch = (businessId: number) => {
        setSwitching(businessId);
        setIsOpen(false);
        
        router.post(`/businesses/${businessId}/switch`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setSwitching(null);
            },
            onError: () => {
                setSwitching(null);
            },
        });
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            active: { label: 'Active', variant: 'default' as const, icon: CheckCircle2, className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
            suspended: { label: 'Suspended', variant: 'outline' as const, icon: AlertCircle, className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
            banned: { label: 'Banned', variant: 'destructive' as const, icon: XCircle, className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.active;
        const Icon = config.icon;

        return (
            <Badge className={config.className} variant="outline">
                <Icon className="mr-1 h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const displayText = currentBusiness 
        ? currentBusiness.name 
        : userBusinesses.length === 0 
            ? 'Add business' 
            : 'Select business';

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-full justify-between h-auto py-3 px-4 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                        {currentBusiness && (currentBusiness.logo && currentBusiness.logo.trim() !== '') ? (
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md overflow-hidden flex-shrink-0 border border-sidebar-border bg-sidebar-primary">
                                <img 
                                    src={currentBusiness.logo} 
                                    alt={currentBusiness.name}
                                    className="w-full h-full object-cover"
                                    onError={(e) => {
                                        // Fallback to initials if image fails to load
                                        const target = e.target as HTMLImageElement;
                                        const parent = target.parentElement;
                                        if (parent) {
                                            target.style.display = 'none';
                                            const initialsSpan = document.createElement('span');
                                            initialsSpan.className = 'text-xs font-semibold text-sidebar-primary-foreground';
                                            initialsSpan.textContent = getBusinessInitials(currentBusiness.name);
                                            parent.appendChild(initialsSpan);
                                        }
                                    }}
                                />
                            </div>
                        ) : currentBusiness ? (
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground flex-shrink-0">
                                <span className="text-xs font-semibold">
                                    {getBusinessInitials(currentBusiness.name)}
                                </span>
                            </div>
                        ) : (
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground flex-shrink-0">
                                <Building2 className="size-4" />
                            </div>
                        )}
                        <div className="flex-1 min-w-0 text-left">
                            <div className="text-sm font-semibold truncate">
                                {displayText}
                            </div>
                            {currentBusiness && (
                                <div className="text-xs text-sidebar-muted-foreground truncate">
                                    {getStatusBadge(currentBusiness.status)}
                                </div>
                            )}
                        </div>
                    </div>
                    <ChevronDown className="ml-2 h-4 w-4 flex-shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <div className="p-2">
                    {userBusinesses.length > 0 ? (
                        <div className="space-y-1">
                            <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                                Switch Business
                            </div>
                            {userBusinesses.map((business) => {
                                const isActive = currentBusiness?.id === business.id;
                                const isSwitching = switching === business.id;

                                return (
                                    <button
                                        key={business.id}
                                        onClick={() => !isActive && handleSwitch(business.id)}
                                        disabled={isActive || isSwitching}
                                        className={`w-full flex items-center justify-between gap-2 px-2 py-2 rounded-md text-sm transition-colors ${
                                            isActive
                                                ? 'bg-accent text-accent-foreground cursor-default'
                                                : 'hover:bg-accent hover:text-accent-foreground cursor-pointer'
                                        } ${isSwitching ? 'opacity-50 cursor-wait' : ''}`}
                                    >
                                        <div className="flex items-center gap-2 flex-1 min-w-0">
                                            {isSwitching ? (
                                                <Loader2 className="h-4 w-4 animate-spin text-primary" />
                                            ) : business.logo && business.logo.trim() !== '' ? (
                                                <div className="flex aspect-square size-6 items-center justify-center rounded-md overflow-hidden flex-shrink-0 border border-border bg-muted">
                                                    <img 
                                                        src={business.logo} 
                                                        alt={business.name}
                                                        className="w-full h-full object-cover"
                                                        onError={(e) => {
                                                            // Fallback to initials if image fails to load
                                                            const target = e.target as HTMLImageElement;
                                                            const parent = target.parentElement;
                                                            if (parent) {
                                                                target.style.display = 'none';
                                                                const initialsSpan = document.createElement('span');
                                                                initialsSpan.className = 'text-[10px] font-semibold text-foreground';
                                                                initialsSpan.textContent = getBusinessInitials(business.name);
                                                                parent.appendChild(initialsSpan);
                                                            }
                                                        }}
                                                    />
                                                </div>
                                            ) : (
                                                <div className="flex aspect-square size-6 items-center justify-center rounded-md bg-primary text-primary-foreground flex-shrink-0">
                                                    <span className="text-[10px] font-semibold">
                                                        {getBusinessInitials(business.name)}
                                                    </span>
                                                </div>
                                            )}
                                            {isActive && !isSwitching && (
                                                <CheckCircle2 className="h-4 w-4 text-primary flex-shrink-0" />
                                            )}
                                            <span className="truncate font-medium">{business.name}</span>
                                        </div>
                                        <div className="flex-shrink-0">
                                            {getStatusBadge(business.status)}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="px-2 py-1.5 text-sm text-muted-foreground text-center">
                            No businesses yet
                        </div>
                    )}
                    <div className="border-t mt-2 pt-2">
                        <Link href="/businesses/create" className="block">
                            <Button
                                variant="ghost"
                                className="w-full justify-start gap-2"
                                onClick={() => setIsOpen(false)}
                            >
                                <Plus className="h-4 w-4" />
                                Add Business
                            </Button>
                        </Link>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
