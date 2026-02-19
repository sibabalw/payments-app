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
import {
    Building2,
    ChevronDown,
    ChevronRight,
    CreditCard,
    HelpCircle,
    Home,
    LayoutGrid,
    Mail,
    Menu,
    PlayCircle,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';
import { useIsMobile } from '@/hooks/use-mobile';
import { trackEvent } from '@/lib/umami';
import { type SharedData } from '@/types';
import AppLogoIcon from './app-logo-icon';
import AppearanceToggleDropdown from './appearance-dropdown';
import { PublicNavigationProgress } from './public-navigation-progress';

const adminDashboardUrl = '/admin';

const productLinks = [
    { href: '/overview', label: 'Overview', icon: LayoutGrid },
    { href: '/features', label: 'Features', icon: Zap },
    { href: '/how-it-works', label: 'How it works', icon: PlayCircle },
    { href: '/pricing', label: 'Pricing', icon: CreditCard },
];

const companyLinks = [
    { href: '/about', label: 'About', icon: Building2 },
    { href: '/faq', label: 'FAQ', icon: HelpCircle },
    { href: '/contact', label: 'Contact', icon: Mail },
];

const navLinkBase =
    'relative after:absolute after:bottom-0 after:left-0 after:h-px after:w-full after:origin-right after:scale-x-0 after:bg-primary after:transition-transform after:duration-200 hover:after:origin-left hover:after:scale-x-100';

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
                    <Link href="/" className="flex items-center gap-2.5">
                        <AppLogoIcon className="h-12 w-12" alt="SwiftPay" />
                        <span className="font-display text-lg font-semibold tracking-tight text-foreground">
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
                                <SheetContent
                                    side="right"
                                    className="flex h-full w-[min(100vw-1.5rem,340px)] max-w-[340px] flex-col gap-0 overflow-hidden border-0 p-0 shadow-2xl sm:max-w-[340px] [background:linear-gradient(to_bottom,var(--hero-gradient-start-value)_0%,oklch(0.98_0.01_260_/_0.95)_35%,var(--background)_70%)] dark:[background:linear-gradient(to_bottom,var(--hero-gradient-start-value)_0%,oklch(0.22_0.02_260)_40%,var(--background)_75%)]"
                                >
                                    <SheetHeader className="relative flex shrink-0 flex-row items-center justify-between border-b border-white/10 px-4 py-3 pr-12 dark:border-white/5">
                                        <SheetTitle className="sr-only">Menu</SheetTitle>
                                        <div className="flex items-center gap-2.5">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 shadow-sm dark:bg-white/10">
                                                <AppLogoIcon className="h-5 w-5" alt="" aria-hidden />
                                            </div>
                                            <span className="bg-gradient-to-r from-primary to-primary/80 bg-clip-text font-display text-lg font-bold tracking-tight text-transparent">
                                                SwiftPay
                                            </span>
                                        </div>
                                    </SheetHeader>
                                    <nav className="relative flex shrink-0 flex-col gap-3 px-3 py-3">
                                        <Link
                                            href="/"
                                            onClick={() => setMobileOpen(false)}
                                            className="group flex items-center gap-3 rounded-lg border border-white/10 bg-white/50 px-3 py-2.5 transition-all hover:border-primary/20 hover:bg-white/80 dark:border-white/5 dark:bg-white/5 dark:hover:border-primary/30 dark:hover:bg-white/10"
                                        >
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 dark:bg-white/15">
                                                <Home className="h-4 w-4 text-primary" />
                                            </div>
                                            <span className="font-display text-sm font-semibold text-foreground">Home</span>
                                            <ChevronRight className="ml-auto h-4 w-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                                        </Link>
                                        <div className="space-y-0.5">
                                            <p className="px-2 font-display text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                                Product
                                            </p>
                                            <div className="space-y-px rounded-lg border border-white/10 bg-white/40 p-1 dark:border-white/5 dark:bg-white/5">
                                                {productLinks.map(({ href, label, icon: Icon }) => (
                                                    <Link
                                                        key={href}
                                                        href={href}
                                                        onClick={() => setMobileOpen(false)}
                                                        className="group flex items-center gap-2.5 rounded-md px-2.5 py-2 transition-colors hover:bg-primary/10 dark:hover:bg-white/10"
                                                    >
                                                        <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 dark:bg-white/10">
                                                            <Icon className="h-3.5 w-3.5 text-primary" />
                                                        </div>
                                                        <span className="text-sm font-medium text-foreground">{label}</span>
                                                        <ChevronRight className="ml-auto h-3.5 w-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="space-y-0.5">
                                            <p className="px-2 font-display text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                                Company
                                            </p>
                                            <div className="space-y-px rounded-lg border border-white/10 bg-white/40 p-1 dark:border-white/5 dark:bg-white/5">
                                                {companyLinks.map(({ href, label, icon: Icon }) => (
                                                    <Link
                                                        key={href}
                                                        href={href}
                                                        onClick={() => setMobileOpen(false)}
                                                        className="group flex items-center gap-2.5 rounded-md px-2.5 py-2 transition-colors hover:bg-primary/10 dark:hover:bg-white/10"
                                                    >
                                                        <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 dark:bg-white/10">
                                                            <Icon className="h-3.5 w-3.5 text-primary" />
                                                        </div>
                                                        <span className="text-sm font-medium text-foreground">{label}</span>
                                                        <ChevronRight className="ml-auto h-3.5 w-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="mt-auto flex flex-col gap-2 border-t border-border/80 pt-3">
                                            {user ? (
                                                <Link
                                                    href={isAdmin ? adminDashboardUrl : dashboard()}
                                                    onClick={() => setMobileOpen(false)}
                                                >
                                                    <Button variant="gradient" size="sm" className="w-full">
                                                        {isAdmin ? 'Go To Admin' : 'Go To Dashboard'}
                                                    </Button>
                                                </Link>
                                            ) : (
                                                <>
                                                    <Link href={login()} onClick={() => setMobileOpen(false)}>
                                                        <Button variant="outline" size="sm" className="w-full">
                                                            Log in
                                                        </Button>
                                                    </Link>
                                                    <Link
                                                        href={register()}
                                                        onClick={() => {
                                                            trackEvent('Get Started clicked', { location: 'nav_mobile' });
                                                            setMobileOpen(false);
                                                        }}
                                                    >
                                                        <Button variant="gradient" size="sm" className="w-full">
                                                            Get Started
                                                        </Button>
                                                    </Link>
                                                </>
                                            )}
                                        </div>
                                    </nav>
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
                                    <Link
                                        href={register()}
                                        onClick={() => trackEvent('Get Started clicked', { location: 'nav' })}
                                    >
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
