import { cn } from '@/lib/utils';
import { ImgHTMLAttributes } from 'react';

const LOGO_SRC = '/logo.svg';

/**
 * SwiftPay logo image. Uses public asset for reliability (works without storage symlink).
 */
export default function AppLogoIcon({
    className,
    alt = 'SwiftPay',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src={LOGO_SRC}
            alt={alt}
            className={cn('shrink-0 object-contain', className)}
            {...props}
        />
    );
}
