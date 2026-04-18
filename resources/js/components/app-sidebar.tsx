import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ScrollText, Sparkles } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as adminLandingEdit } from '@/routes/admin/landing';
import { index as adminLogsIndex } from '@/routes/admin/logs';
import type { NavItem } from '@/types';

function useMainNavItems(): NavItem[] {
    const { auth } = usePage().props;

    return [
        {
            title: 'Dashboard',
            href: dashboard.url(),
            icon: LayoutGrid,
        },
        ...(auth.user?.is_admin
            ? [
                  {
                      title: 'Analysis activity',
                      href: adminLogsIndex.url(),
                      icon: ScrollText,
                  } satisfies NavItem,
                  {
                      title: 'Landing page',
                      href: adminLandingEdit.url(),
                      icon: Sparkles,
                  } satisfies NavItem,
              ]
            : []),
    ];
}

export function AppSidebar() {
    const mainNavItems = useMainNavItems();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard.url()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="gap-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
