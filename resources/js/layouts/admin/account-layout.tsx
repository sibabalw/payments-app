import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn, toUrl } from '@/lib/utils';
import { useActiveUrl } from '@/hooks/use-active-url';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const accountNavItems: NavItem[] = [
    { title: 'Profile', href: '/admin/account/profile', icon: null },
    { title: 'Password', href: '/admin/account/password', icon: null },
    { title: 'Two-Factor Auth', href: '/admin/account/two-factor', icon: null },
    { title: 'Appearance', href: '/admin/account/appearance', icon: null },
];

export default function AdminAccountLayout({ children }: PropsWithChildren) {
    const { urlIsActive } = useActiveUrl();

    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <Heading
                title="Account"
                description="Manage your admin profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0" aria-label="Account settings">
                        {accountNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': urlIsActive(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon ? (
                                        <item.icon className="h-4 w-4" />
                                    ) : null}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">
                    <section className="space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
