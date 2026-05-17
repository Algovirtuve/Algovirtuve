import { Head, router, usePage } from '@inertiajs/react';
import { viewAdminPage } from '@/actions/App/Http/Controllers/Administration/admin_controller';
import { showProductsPlanPage } from '@/actions/App/Http/Controllers/Shopping_management/shopping_controller';
import { Button } from '@/components/ui/button';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';

export default function MainPage() {
    const { auth } = usePage().props;

    const onShoppingPlanGenerate = () => {
        router.get(showProductsPlanPage().url);
    };

    const onViewAdminPage = () => {
        router.visit(viewAdminPage().url);
    };

    return (
        <>
            <Head title="Main Page" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {auth.is_administrator && (
                    <div className="flex items-center justify-end">
                        <Button onClick={onViewAdminPage}>Admin</Button>
                    </div>
                )}

                <div className="flex items-center justify-end">
                    <Button onClick={onShoppingPlanGenerate}>
                        Generate shopping plan
                    </Button>
                </div>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-screen flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

MainPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
