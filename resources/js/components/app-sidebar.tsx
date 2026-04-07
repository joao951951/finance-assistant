import { Link } from '@inertiajs/react';
import {
    BookOpen,
    ChevronRight,
    FolderGit2,
    KeyRound,
    LayoutGrid,
    MessageSquare,
    Plus,
    Receipt,
    Upload,
} from 'lucide-react';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import ApiController from '@/actions/App/Http/Controllers/Settings/ApiController';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const topNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard.url(),
        icon: LayoutGrid,
    },
];

const bottomNavItems: NavItem[] = [
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
    {
        title: 'API / IA',
        href: ApiController.edit.url(),
        icon: KeyRound,
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
                <NavMain items={topNavItems} />

                {/* Transações — collapsible with sub-items */}
                <div className="px-2 py-0">
                    <SidebarMenu>
                        <Collapsible defaultOpen className="group/collapsible">
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton tooltip="Transações">
                                        <Receipt />
                                        <span>Transações</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton asChild>
                                                <Link
                                                    href={TransactionController.index.url()}
                                                    prefetch
                                                >
                                                    Todas as transações
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton asChild>
                                                <Link
                                                    href={
                                                        TransactionController.index.url() +
                                                        '?new=1'
                                                    }
                                                >
                                                    <Plus className="size-3.5" />
                                                    Nova transação
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    </SidebarMenu>
                </div>

                <NavMain items={bottomNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
