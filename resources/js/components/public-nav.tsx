import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import AppLogoIcon from './app-logo-icon';
import AppearanceToggleDropdown from './appearance-dropdown';

export function PublicNav() {
    return (
        <nav className="border-b bg-background">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 items-center justify-between">
                    <Link href="/" className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="h-5 w-5 fill-current" />
                        </div>
                        <span className="text-2xl font-bold text-primary">Swift Pay</span>
                    </Link>
                    <div className="flex items-center gap-4">
                        <Link
                            href="/features"
                            className="text-sm font-medium text-foreground/80 hover:text-foreground"
                        >
                            Features
                        </Link>
                        <Link
                            href="/pricing"
                            className="text-sm font-medium text-foreground/80 hover:text-foreground"
                        >
                            Pricing
                        </Link>
                        <Link
                            href="/about"
                            className="text-sm font-medium text-foreground/80 hover:text-foreground"
                        >
                            About
                        </Link>
                        <Link
                            href="/contact"
                            className="text-sm font-medium text-foreground/80 hover:text-foreground"
                        >
                            Contact
                        </Link>
                        <AppearanceToggleDropdown />
                        <Link href={login()}>
                            <Button variant="ghost" size="sm">
                                Log in
                            </Button>
                        </Link>
                        <Link href={register()}>
                            <Button size="sm">Get Started</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </nav>
    );
}
