import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index, store } from '@/routes/preferences';

type RecipeOption = {
    id: number;
    title: string;
    instructions: string;
    preparation_time_minutes: number;
    servings: number;
    difficulty: string;
    calorie_count: number;
    status: string;
};

type StatusOption = {
    value: 'liked' | 'disliked';
    label: string;
};

export default function CreatePreference({
    recipes,
    statuses,
}: {
    recipes: RecipeOption[];
    statuses: StatusOption[];
}) {
    const [recipeId, setRecipeId] = useState<string>(
        recipes[0] ? String(recipes[0].id) : '',
    );
    const [status, setStatus] = useState<string>(statuses[0]?.value ?? 'liked');

    const selectedRecipe = recipes.find(
        (recipe) => String(recipe.id) === recipeId,
    );

    return (
        <>
            <Head title="Add preference" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Add recipe preference"
                    description="Choose a published recipe and save whether you like it or dislike it."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Preference details</CardTitle>
                            <CardDescription>
                                The form follows the add-preference flow from
                                the sequence diagram.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <Form
                                {...store.form()}
                                className="space-y-6"
                                disableWhileProcessing
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="recipe_id"
                                            value={recipeId}
                                        />
                                        <input
                                            type="hidden"
                                            name="status"
                                            value={status}
                                        />

                                        <div className="space-y-2">
                                            <label
                                                className="text-sm font-medium"
                                                htmlFor="recipe-select"
                                            >
                                                Recipe
                                            </label>
                                            <Select
                                                value={recipeId}
                                                onValueChange={setRecipeId}
                                                disabled={recipes.length === 0}
                                            >
                                                <SelectTrigger
                                                    id="recipe-select"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Select a recipe" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {recipes.map((recipe) => (
                                                        <SelectItem
                                                            key={recipe.id}
                                                            value={String(
                                                                recipe.id,
                                                            )}
                                                        >
                                                            {recipe.title}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.recipe_id}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <label
                                                className="text-sm font-medium"
                                                htmlFor="status-select"
                                            >
                                                Preference status
                                            </label>
                                            <Select
                                                value={status}
                                                onValueChange={setStatus}
                                            >
                                                <SelectTrigger
                                                    id="status-select"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Select a status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {statuses.map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.status}
                                            />
                                        </div>

                                        <div className="flex flex-wrap gap-3">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing || !recipeId
                                                }
                                            >
                                                {processing
                                                    ? 'Saving...'
                                                    : 'Create preference'}
                                            </Button>

                                            <Button variant="outline" asChild>
                                                <Link href={index()} prefetch>
                                                    Cancel
                                                </Link>
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recipe preview</CardTitle>
                            <CardDescription>
                                Review the selected recipe before saving your
                                preference.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            {selectedRecipe ? (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <h3 className="text-lg font-semibold">
                                            {selectedRecipe.title}
                                        </h3>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {selectedRecipe.instructions}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2 text-xs">
                                        <Badge variant="outline">
                                            {selectedRecipe.difficulty}
                                        </Badge>
                                        <Badge variant="outline">
                                            {
                                                selectedRecipe.preparation_time_minutes
                                            }{' '}
                                            min
                                        </Badge>
                                        <Badge variant="outline">
                                            {selectedRecipe.servings} servings
                                        </Badge>
                                        <Badge variant="outline">
                                            {selectedRecipe.calorie_count} kcal
                                        </Badge>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                                    No published recipes are currently available
                                    for new preferences.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CreatePreference.layout = {
    breadcrumbs: [
        {
            title: 'Preferences',
            href: index(),
        },
        {
            title: 'Add preference',
            href: create(),
        },
    ],
};
