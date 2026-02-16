import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu } from 'lucide-react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';
import { useIsMobile } from '@/hooks/use-mobile';
import { type SharedData } from '@/types';
import AppLogoIcon from './app-logo-icon';
import AppearanceToggleDropdown from './appearance-dropdown';
import { PublicNavigationProgress } from './public-navigation-progress';

const adminDashboardUrl = '/admin';

const productLinks = [
    { href: '/overview', label: 'Overview' },
    { href: '/features', label: 'Features' },
    { href: '/how-it-works', label: 'How it works' },
    { href: '/pricing', label: 'Pricing' },
];

const companyLinks = [
    { href: '/about', label: 'About' },
    { href: '/faq', label: 'FAQ' },
    { href: '/contact', label: 'Contact' },
];

const navLinkBase =
    'relative after:absolute after:bottom-0 after:left-0 after:h-px after:w-full after:origin-right after:scale-x-0 after:bg-primary after:transition-transform after:duration-200 hover:after:origin-left hover:after:scale-x-100';

function NavLinks({ onNavigate, mobile }: { onNavigate?: () => void; mobile?: boolean }) {
    const linkClass = mobile
        ? 'block rounded-md px-3 py-2 text-base font-medium text-foreground/80 transition-colors hover:bg-muted hover:text-foreground'
        : `rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground/80 transition-colors hover:bg-muted/80 hover:text-foreground ${navLinkBase}`;
    return (
        <>
            {productLinks.map(({ href, label }) => (
                <Link
                    key={href}
                    href={href}
                    onClick={onNavigate}
                    className={linkClass}
                >
                    {label}
                </Link>
            ))}
            {companyLinks.map(({ href, label }) => (
                <Link
                    key={href}
                    href={href}
                    onClick={onNavigate}
                    className={linkClass}
                >
                    {label}
                </Link>
            ))}
        </>
    );
}

export function PublicNav() {
    const isMobile = useIsMobile();
    const [mobileOpen, setMobileOpen] = useState(false);
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user;
    const isAdmin = user?.is_admin === true;

    return (
        <>
            <nav className="sticky top-0 z-40 border-b border-white/10 bg-background/70 backdrop-blur-xl dark:border-white/5 dark:bg-background/80 dark:shadow-[0_1px_0_0_oklch(0.32_0.02_260_/_0.5)]">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 items-center justify-between">
                    <Link href="/" className="flex items-center gap-2">
                        <AppLogoIcon className="h-8 w-8" aria-hidden />
                        <span className="font-display text-2xl font-bold tracking-tight text-primary">
                            SwiftPay
                        </span>
                    </Link>

                    {isMobile ? (
                        <div className="flex items-center gap-2">
                            <AppearanceToggleDropdown />
                            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                                <SheetTrigger asChild>
                                    <Button variant="ghost" size="icon" aria-label="Open menu">
                                        <Menu className="h-5 w-5" />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="right" className="flex flex-col gap-6">
                                    <SheetHeader>
                                        <SheetTitle className="sr-only">Menu</SheetTitle>
                                    </SheetHeader>
                                    <div className="flex flex-col gap-1">
                                        <Link
                                            href="/"
                                            onClick={() => setMobileOpen(false)}
                                            className="block rounded-md px-3 py-2 text-base font-medium text-foreground/80 transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            Home
                                        </Link>
                                        <NavLinks onNavigate={() => setMobileOpen(false)} mobile />
                                    </div>
                                    <div className="mt-auto flex flex-col gap-2 border-t pt-4">
                                        {user ? (
                                            <Link
                                                href={isAdmin ? adminDashboardUrl : dashboard()}
                                                onClick={() => setMobileOpen(false)}
                                            >
                                                <Button variant="gradient" className="w-full">
                                                    {isAdmin ? 'Go To Admin' : 'Go To Dashboard'}
                                                </Button>
                                            </Link>
                                        ) : (
                                            <>
                                                <Link href={login()} onClick={() => setMobileOpen(false)}>
                                                    <Button variant="outline" className="w-full">
                                                        Log in
                                                    </Button>
                                                </Link>
                                                <Link href={register()} onClick={() => setMobileOpen(false)}>
                                                    <Button variant="gradient" className="w-full">
                                                        Get Started
                                                    </Button>
                                                </Link>
                                            </>
                                        )}
                                    </div>
                                </SheetContent>
                            </Sheet>
                        </div>
                    ) : (
                        <div className="flex items-center gap-6">
                            <Link
                                href="/"
                                className={`rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground/80 transition-colors hover:bg-muted/80 hover:text-foreground ${navLinkBase}`}
                            >
                                Home
                            </Link>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground/80 transition-colors hover:bg-muted/80 hover:text-foreground"
                                    >
                                        Product
                                        <ChevronDown className="h-4 w-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="min-w-[10rem] rounded-lg shadow-lg"
                                >
                                    {productLinks.map(({ href, label }) => (
                                        <DropdownMenuItem key={href} asChild>
                                            <Link href={href}>{label}</Link>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Link
                                href="/about"
                                className={`rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground/80 transition-colors hover:bg-muted/80 hover:text-foreground ${navLinkBase}`}
                            >
                                About
                            </Link>
                            <Link
                                href="/faq"
                                className={`rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground/80 transition-colors hover:bg-muted/80 hover:text-foreground ${navLinkBase}`}
                            >
                                FAQ
                            </Link>
                            <Link
                                href="/contact"
                                className={`rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground/80 transition-colors hover:bg-muted/80 hover:text-foreground ${navLinkBase}`}
                            >
                                Contact
                            </Link>
                            <AppearanceToggleDropdown />
                            {user ? (
                                <Link href={isAdmin ? adminDashboardUrl : dashboard()}>
                                    <Button variant="gradient" size="sm">
                                        {isAdmin ? 'Go To Admin' : 'Go To Dashboard'}
                                    </Button>
                                </Link>
                            ) : (
                                <>
                                    <Link href={login()}>
                                        <Button variant="ghost" size="sm">
                                            Log in
                                        </Button>
                                    </Link>
                                    <Link href={register()}>
                                        <Button variant="gradient" size="sm">
                                            Get Started
                                        </Button>
                                    </Link>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </nav>
            <PublicNavigationProgress />
        </>
    );
}
