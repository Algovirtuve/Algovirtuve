import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Heart,
    LayoutGrid,
    Leaf,
    Sparkles,
    Wrench,
} from 'lucide-react';
import { suggestions as onRecipeSuggestion } from '@/actions/App/Http/Controllers/Personalization/personalization_controller';
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
import { index as onIngredientPage } from '@/routes/ingredients';
import { index as preferences } from '@/routes/preferences';
import { index as onRecipeClick } from '@/routes/recipes';
import { index as onToolPage } from '@/routes/tools';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Preferences',
        href: preferences(),
        icon: Heart,
    },
    {
        title: 'Tools',
        href: onToolPage(),
        icon: Wrench,
    },
    {
        title: 'Ingredients',
        href: onIngredientPage(),
        icon: Leaf,
    },
    {
        title: 'Recipes',
        href: onRecipeClick(),
        icon: BookOpen,
    },
    {
        title: 'Recipe Suggestions',
        href: onRecipeSuggestion(),
        icon: Sparkles,
    },
];

export function NavigationBar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
