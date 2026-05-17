import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    create as showIngredientCreationPage,
    destroy as deleteIngredient,
} from '@/routes/ingredients';

type IngredientItem = {
    id: number;
    category: string;
    category_label: string;
};

export default function IngredientPage({
    ingredients,
}: {
    ingredients: IngredientItem[];
}) {
    const [ingredientToRemove, setIngredientToRemove] =
        useState<IngredientItem | null>(null);

    const startIngredientDeletion = (ingredient: IngredientItem) => {
        setIngredientToRemove(ingredient);
    };

    const startIngredientCreation = () => {
        showIngredientCreationPage.form();
    };

    return (
        <>
            <Head title="Ingredients" />

            <div className="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm tracking-[0.24em] text-muted-foreground uppercase">
                            Ingredients
                        </p>
                        <CardTitle className="mt-2 text-3xl">
                            My ingredients
                        </CardTitle>
                    </div>
                    <Button onClick={() => startIngredientCreation()}>
                        <Plus />
                        Add ingredient
                    </Button>
                </div>

                {ingredients.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                You have not added any ingredients yet. Create
                                one to start building your recipe profile.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {ingredients.map((ingredient) => (
                            <Card key={ingredient.id}>
                                <CardHeader>
                                    <CardTitle>
                                        {ingredient.category_label}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Category: {ingredient.category_label}
                                    </p>
                                </CardContent>
                                <CardFooter className="justify-end gap-2">
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            startIngredientDeletion(ingredient)
                                        }
                                    >
                                        Remove
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
