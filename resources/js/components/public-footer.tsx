import { Link } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

const footerLinkClass =
    'relative inline-block text-muted-foreground transition-colors hover:text-accent-public after:absolute after:bottom-0 after:left-0 after:h-px after:w-full after:origin-right after:scale-x-0 after:bg-accent-public after:transition-transform after:duration-200 hover:after:origin-left hover:after:scale-x-100';

const mainLinks = [
    { href: '/overview', label: 'Overview' },
    { href: '/features', label: 'Features' },
    { href: '/how-it-works', label: 'How it works' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/about', label: 'About' },
    { href: '/contact', label: 'Contact' },
    { href: '/faq', label: 'FAQ' },
];

export function PublicFooter() {
    return (
        <footer className="border-t border-border/60 bg-gradient-to-b from-transparent to-muted/30 py-10 md:py-16 dark:to-muted/20 dark:border-white/5">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {/* Mobile: single column, brand + wrap links + legal inline */}
            <div className="flex flex-col items-center gap-6 text-center md:hidden">
                <div className="flex flex-col items-center gap-2">
                    <Link href="/" className="flex flex-col items-center gap-2">
                        <AppLogoIcon className="h-11 w-11" alt="SwiftPay" />
                        <h3 className="font-display text-lg font-bold tracking-tight text-foreground">
                            SwiftPay
                        </h3>
                    </Link>
                    <p className="max-w-[280px] text-sm leading-relaxed text-muted-foreground">
                        Payment and payroll for South African businesses.
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm">
                    {mainLinks.map(({ href, label }) => (
                        <Link
                            key={href}
                            href={href}
                            className={footerLinkClass}
                        >
                            {label}
                        </Link>
                    ))}
                </div>
                <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <Link href="/privacy" className={footerLinkClass}>
                        Privacy
                    </Link>
                    <span className="text-border">·</span>
                    <Link href="/terms" className={footerLinkClass}>
                        Terms
                    </Link>
                </div>
                <p className="text-xs text-muted-foreground/90">
                    &copy; {new Date().getFullYear()} SwiftPay
                </p>
            </div>

            {/* Desktop: 4-column layout */}
            <div className="hidden md:grid md:grid-cols-4 md:gap-12">
                <div className="md:pr-4">
                    <Link href="/" className="flex items-center gap-3">
                        <AppLogoIcon className="h-10 w-10 shrink-0" alt="SwiftPay" />
                        <h3 className="font-display text-xl font-bold tracking-tight text-foreground">
                            SwiftPay
                        </h3>
                    </Link>
                    <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                        Automated payment and payroll solutions for South African businesses.
                    </p>
                </div>
                <div>
                    <h4 className="text-sm font-semibold uppercase tracking-wider text-foreground">
                        Product
                    </h4>
                    <ul className="mt-4 space-y-3 text-sm">
                        <li><Link href="/overview" className={footerLinkClass}>Overview</Link></li>
                        <li><Link href="/features" className={footerLinkClass}>Features</Link></li>
                        <li><Link href="/how-it-works" className={footerLinkClass}>How it works</Link></li>
                        <li><Link href="/pricing" className={footerLinkClass}>Pricing</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 className="text-sm font-semibold uppercase tracking-wider text-foreground">
                        Company
                    </h4>
                    <ul className="mt-4 space-y-3 text-sm">
                        <li><Link href="/about" className={footerLinkClass}>About</Link></li>
                        <li><Link href="/contact" className={footerLinkClass}>Contact</Link></li>
                        <li><Link href="/faq" className={footerLinkClass}>FAQ</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 className="text-sm font-semibold uppercase tracking-wider text-foreground">
                        Legal
                    </h4>
                    <ul className="mt-4 space-y-3 text-sm">
                        <li><Link href="/privacy" className={footerLinkClass}>Privacy Policy</Link></li>
                        <li><Link href="/terms" className={footerLinkClass}>Terms of Service</Link></li>
                    </ul>
                </div>
            </div>
            <div className="mt-10 hidden border-t border-border/60 pt-8 text-center text-sm text-muted-foreground md:mt-12 md:block md:pt-10">
                <p>&copy; {new Date().getFullYear()} SwiftPay. All rights reserved.</p>
            </div>
            </div>
        </footer>
    );
}
