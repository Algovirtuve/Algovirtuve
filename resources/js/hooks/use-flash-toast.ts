import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

function getFirstErrorMessage(errors: Record<string, string>): string | null {
    const firstError = Object.values(errors)[0];

    return firstError ?? null;
}

export function useFlashToast(): void {
    useEffect(() => {
        const removeSuccessListener = router.on('success', (event) => {
            const data = event.detail.page.props.flash?.toast as
                | FlashToast
                | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });

        const removeErrorListener = router.on('error', (event) => {
            const message = getFirstErrorMessage(
                event.detail.errors as Record<string, string>,
            );

            if (!message) {
                return;
            }

            toast.error(message);
        });

        const removeNetworkErrorListener = router.on(
            'networkError',
            (event) => {
                event.preventDefault();

                toast.error('Something went wrong. Please try again.');
            },
        );

        return () => {
            removeSuccessListener();
            removeErrorListener();
            removeNetworkErrorListener();
        };
    }, []);
}
