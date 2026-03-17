import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, MessageSquare, Upload } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard.url(),
        icon: LayoutGrid,
    },
    {
        title: 'Importações de extratos',
        href: ImportController.index.url(),
        icon: Upload,
    },
    {
        title: 'Chat com IA',
        href: ConversationController.index.url(),
        icon: MessageSquare,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repositório do projeto',
        href: 'https://github.com/joao951951/finance-assistant',
        icon: FolderGit2,
    },
    {
        title: 'Documentação do projeto',
        href: 'https://www.notion.so/Finance-Assistant-Documenta-o-do-Projeto-3267b0bdfcab81a0bed8e2686413ceba?source=copy_link',
        icon: BookOpen,
    },
];

export function AppSidebar() {
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

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
