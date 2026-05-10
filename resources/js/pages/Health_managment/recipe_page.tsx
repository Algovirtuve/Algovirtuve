import { useState } from 'react';
import Heading from '@/components/heading';
import { RecipeCard } from '@/components/recipes/recipe-card';
import { RecipeDeleteDialog } from '@/components/recipes/recipe-delete-dialog';
import { RecipeEditDialog } from '@/components/recipes/recipe-edit-dialog';
import { RecipeRequestDialog } from '@/components/recipes/recipe-request-dialog';
import type { EnumOption, RecipeListItem } from '@/components/recipes/types';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/recipes';

export default function RecipesIndex({
    recipes,
    difficulties,
    dietTypes,
    meals,
}: {
    recipes: RecipeListItem[];
    difficulties: EnumOption[];
    dietTypes: EnumOption[];
    meals: EnumOption[];
}) {
    const [recipeBeingEdited, onOpenEditRecipe] =
        useState<RecipeListItem | null>(null);
    const [recipeBeingDeleted, onDelete] = useState<RecipeListItem | null>(
        null,
    );
    const [isCreateRequestOpen, setIsCreateRequestOpen] = useState(false);

    const onRecipeRequest = () => {
        setIsCreateRequestOpen(true);
    };

    const onCreateRequestCancel = () => {
        setIsCreateRequestOpen(false);
    };

    return (
        <>
            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        title="Recipe page"
                        description="Review your created recipes, update them in an edit modal, or remove them with confirmation."
                    />
                    <Button type="button" onClick={onRecipeRequest}>
                        Create recipe request
                    </Button>
                </div>

                {recipes.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle>No recipes yet</CardTitle>
                            <CardDescription>
                                Your recipe list will appear here once recipes
                                are created.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-4 xl:grid-cols-2">
                        {recipes.map((recipe) => (
                            <RecipeCard
                                key={recipe.id}
                                recipe={recipe}
                                onEdit={onOpenEditRecipe}
                                onDelete={onDelete}
                            />
                        ))}
                    </div>
                )}
            </div>

            <RecipeEditDialog
                recipe={recipeBeingEdited}
                difficulties={difficulties}
                dietTypes={dietTypes}
                meals={meals}
                onCloseEditRecipe={() => onOpenEditRecipe(null)}
            />

            <RecipeDeleteDialog
                recipe={recipeBeingDeleted}
                onCancelDelete={() => onDelete(null)}
            />

            <RecipeRequestDialog
                isOpen={isCreateRequestOpen}
                difficulties={difficulties}
                dietTypes={dietTypes}
                meals={meals}
                onCreateRequestCancel={onCreateRequestCancel}
            />
        </>
    );
}

RecipesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Recipes',
            href: index(),
        },
    ],
};
