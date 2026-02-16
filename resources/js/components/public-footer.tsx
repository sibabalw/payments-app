import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

const footerLinkClass =
    'relative inline-block text-muted-foreground transition-colors hover:text-accent-public after:absolute after:bottom-0 after:left-0 after:h-px after:w-full after:origin-right after:scale-x-0 after:bg-accent-public after:transition-transform after:duration-200 hover:after:origin-left hover:after:scale-x-100';

export function PublicFooter() {
    return (
        <footer className="border-t border-border/60 bg-gradient-to-b from-transparent to-muted/50 py-16 dark:to-muted/40 dark:border-white/5">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-12 md:grid-cols-4">
                    <div className="md:pr-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <Building2 className="h-6 w-6" />
                            </div>
                            <h3 className="font-display text-xl font-bold tracking-tight text-foreground">
                                SwiftPay
                            </h3>
                        </div>
                        <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                            Automated payment and payroll solutions for South African businesses.
                        </p>
                    </div>
                    <div>
                        <h4 className="text-sm font-semibold uppercase tracking-wider text-foreground">
                            Product
                        </h4>
                        <ul className="mt-4 space-y-3 text-sm">
                            <li>
                                <Link href="/overview" className={footerLinkClass}>
                                    Overview
                                </Link>
                            </li>
                            <li>
                                <Link href="/features" className={footerLinkClass}>
                                    Features
                                </Link>
                            </li>
                            <li>
                                <Link href="/how-it-works" className={footerLinkClass}>
                                    How it works
                                </Link>
                            </li>
                            <li>
                                <Link href="/pricing" className={footerLinkClass}>
                                    Pricing
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="text-sm font-semibold uppercase tracking-wider text-foreground">
                            Company
                        </h4>
                        <ul className="mt-4 space-y-3 text-sm">
                            <li>
                                <Link href="/about" className={footerLinkClass}>
                                    About
                                </Link>
                            </li>
                            <li>
                                <Link href="/contact" className={footerLinkClass}>
                                    Contact
                                </Link>
                            </li>
                            <li>
                                <Link href="/faq" className={footerLinkClass}>
                                    FAQ
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="text-sm font-semibold uppercase tracking-wider text-foreground">
                            Legal
                        </h4>
                        <ul className="mt-4 space-y-3 text-sm">
                            <li>
                                <Link href="/privacy" className={footerLinkClass}>
                                    Privacy Policy
                                </Link>
                            </li>
                            <li>
                                <Link href="/terms" className={footerLinkClass}>
                                    Terms of Service
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
                <div className="mt-12 border-t border-border/60 pt-10 text-center text-sm text-muted-foreground">
                    <p>&copy; {new Date().getFullYear()} SwiftPay. All rights reserved.</p>
                </div>
            </div>
        </footer>
    );
}
