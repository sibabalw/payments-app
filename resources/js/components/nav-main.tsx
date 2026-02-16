import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useActiveUrl } from '@/hooks/use-active-url';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { urlIsActive } = useActiveUrl();
    // All items with children are open by default
    const [openItems, setOpenItems] = useState<Record<string, boolean>>({});

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasChildren = item.items && item.items.length > 0;
                    const isParentActive =
                        hasChildren &&
                        item.items!.some((child) => child.href && urlIsActive(child.href));

                    if (hasChildren) {
                        const firstChildHref = item.items![0]?.href;
                        // Open if explicitly set, or if a child is currently active
                        const isOpen = openItems[item.title] ?? isParentActive;

                        return (
                            <Collapsible
                                key={item.title}
                                open={isOpen}
                                onOpenChange={(open) => {
                                    setOpenItems((prev) => ({
                                        ...prev,
                                        [item.title]: open,
                                    }));
                                }}
                                className="group/collapsible"
                            >
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isParentActive}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link
                                            href={firstChildHref || '#'}
                                            onClick={(e) => {
                                                // Expand the collapsible if closed, or toggle if open
                                                const newOpenState = !isOpen;
                                                setOpenItems((prev) => ({
                                                    ...prev,
                                                    [item.title]: newOpenState,
                                                }));
                                                
                                                // If already on a child page, prevent navigation and just toggle
                                                if (isParentActive) {
                                                    e.preventDefault();
                                                }
                                            }}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            {item.badge !== undefined && item.badge !== null && (
                                                <SidebarMenuBadge className="bg-primary/10 text-primary text-xs">
                                                    {item.badge}
                                                </SidebarMenuBadge>
                                            )}
                                            <ChevronDown className="ml-auto size-4 shrink-0 transition-transform group-data-[state=closed]/collapsible:rotate-[-90deg]" />
                                        </Link>
                                    </SidebarMenuButton>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {item.items!.map((child) => (
                                                <SidebarMenuSubItem key={child.title}>
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={
                                                            child.href
                                                                ? urlIsActive(child.href)
                                                                : false
                                                        }
                                                    >
                                                        <Link href={child.href!}>
                                                            <span>{child.title}</span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        );
                    }

                    if (!item.href) {
                        return null;
                    }

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={urlIsActive(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href}>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                    {item.badge !== undefined && item.badge !== null && (
                                        <SidebarMenuBadge className="bg-primary/10 text-primary text-xs">
                                            {item.badge}
                                        </SidebarMenuBadge>
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
