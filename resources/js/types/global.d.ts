import type { Auth } from '@/types/auth';
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: {
                toast?: FlashToast | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
