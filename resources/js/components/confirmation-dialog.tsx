import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { AlertTriangle, Info, HelpCircle } from 'lucide-react';
import { ReactNode } from 'react';

interface ConfirmationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'destructive' | 'default' | 'warning' | 'info';
    processing?: boolean;
    icon?: ReactNode;
}

export default function ConfirmationDialog({
    open,
    onOpenChange,
    onConfirm,
    title,
    description,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'destructive',
    processing = false,
    icon,
}: ConfirmationDialogProps) {
    const getIcon = () => {
        if (icon) {
            return icon;
        }

        switch (variant) {
            case 'destructive':
                return <AlertTriangle className="h-6 w-6 text-red-600 dark:text-red-400" />;
            case 'warning':
                return <AlertTriangle className="h-6 w-6 text-yellow-600 dark:text-yellow-400" />;
            case 'info':
                return <Info className="h-6 w-6 text-blue-600 dark:text-blue-400" />;
            default:
                return <HelpCircle className="h-6 w-6 text-gray-600 dark:text-gray-400" />;
        }
    };

    const getIconBg = () => {
        switch (variant) {
            case 'destructive':
                return 'bg-red-100 dark:bg-red-900/20';
            case 'warning':
                return 'bg-yellow-100 dark:bg-yellow-900/20';
            case 'info':
                return 'bg-blue-100 dark:bg-blue-900/20';
            default:
                return 'bg-gray-100 dark:bg-gray-900/20';
        }
    };

    const getConfirmVariant = () => {
        return variant === 'destructive' || variant === 'warning' ? 'destructive' : 'default';
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className={`mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full ${getIconBg()}`}>
                        {getIcon()}
                    </div>
                    <DialogTitle className="text-center text-xl">
                        {title}
                    </DialogTitle>
                    {description && (
                        <DialogDescription className="text-center">
                            {description}
                        </DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter className="gap-3 sm:gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="w-full sm:w-auto"
                    >
                        {cancelText}
                    </Button>
                    <Button
                        type="button"
                        variant={getConfirmVariant()}
                        onClick={onConfirm}
                        disabled={processing}
                        className="w-full sm:w-auto"
                    >
                        {processing && <Spinner className="mr-2 h-4 w-4" />}
                        {confirmText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
