import { router } from '@inertiajs/react';
import type { RecipeListItem } from '@/components/recipes/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/recipes';

export function RecipeDeleteDialog({
    recipe,
    onCancelDelete,
}: {
    recipe: RecipeListItem | null;
    onCancelDelete: () => void;
}) {
    const onConfirmDelete = () => {
        if (!recipe) {
            return;
        }

        router.delete(destroy.url(recipe.id), {
            preserveScroll: true,
            onSuccess: onCancelDelete,
        });
    };

    return (
        <Dialog
            open={recipe !== null}
            onOpenChange={(open) => !open && onCancelDelete()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete recipe</DialogTitle>
                    <DialogDescription>
                        {recipe
                            ? `Delete "${recipe.title}"? This action cannot be undone.`
                            : 'Delete this recipe?'}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancelDelete}
                    >
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={onConfirmDelete}>
                        Confirm delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
