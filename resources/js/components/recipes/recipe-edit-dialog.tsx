import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { emptyRecipeForm, toRecipeForm } from '@/components/recipes/types';
import type { EnumOption, RecipeListItem } from '@/components/recipes/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/recipes';

export function RecipeEditDialog({
    recipe,
    difficulties,
    dietTypes,
    meals,
    onCloseEditRecipe,
}: {
    recipe: RecipeListItem | null;
    difficulties: EnumOption[];
    dietTypes: EnumOption[];
    meals: EnumOption[];
    onCloseEditRecipe: () => void;
}) {
    const { data, setData, patch, processing, errors, reset, clearErrors } =
        useForm(emptyRecipeForm);

    const isOpen = recipe !== null;

    useEffect(() => {
        if (!recipe) {
            clearErrors();
            reset();

            return;
        }

        const formData = toRecipeForm(recipe);

        clearErrors();
        reset();
        setData('title', formData.title);
        setData('instructions', formData.instructions);
        setData('preparation_time', formData.preparation_time);
        setData('servings', formData.servings);
        setData('difficulty', formData.difficulty);
        setData('calorie_intake', formData.calorie_intake);
        setData('diet_type', formData.diet_type);
        setData('meal', formData.meal);
    }, [recipe, clearErrors, reset, setData]);

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            clearErrors();
            reset();
            onCloseEditRecipe();
        }
    };

    const onSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!recipe) {
            return;
        }

        patch(update.url(recipe.id), {
            preserveScroll: true,
            onSuccess: () => {
                clearErrors();
                reset();
                onCloseEditRecipe();
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit recipe</DialogTitle>
                    <DialogDescription>
                        Update the selected recipe and submit the form to save
                        changes.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="recipe-title">Title</Label>
                        <Input
                            id="recipe-title"
                            value={data.title}
                            onChange={(event) =>
                                setData('title', event.target.value)
                            }
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="recipe-instructions">
                            Instructions
                        </Label>
                        <textarea
                            id="recipe-instructions"
                            className="min-h-28 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs ring-offset-background outline-hidden placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            value={data.instructions}
                            onChange={(event) =>
                                setData('instructions', event.target.value)
                            }
                        />
                        <InputError message={errors.instructions} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="recipe-preparation-time">
                                Preparation time
                            </Label>
                            <Input
                                id="recipe-preparation-time"
                                value={data.preparation_time}
                                onChange={(event) =>
                                    setData(
                                        'preparation_time',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.preparation_time} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="recipe-servings">Servings</Label>
                            <Input
                                id="recipe-servings"
                                type="number"
                                min={1}
                                value={data.servings}
                                onChange={(event) =>
                                    setData(
                                        'servings',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.servings} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Difficulty</Label>
                            <Select
                                value={data.difficulty}
                                onValueChange={(value) =>
                                    setData('difficulty', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select difficulty" />
                                </SelectTrigger>
                                <SelectContent>
                                    {difficulties.map((difficulty) => (
                                        <SelectItem
                                            key={difficulty.value}
                                            value={difficulty.value}
                                        >
                                            {difficulty.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.difficulty} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Diet type</Label>
                            <Select
                                value={data.diet_type}
                                onValueChange={(value) =>
                                    setData('diet_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select diet type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dietTypes.map((dietType) => (
                                        <SelectItem
                                            key={dietType.value}
                                            value={dietType.value}
                                        >
                                            {dietType.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.diet_type} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="recipe-calorie-intake">
                                Calorie intake
                            </Label>
                            <Input
                                id="recipe-calorie-intake"
                                type="number"
                                min={0}
                                value={data.calorie_intake}
                                onChange={(event) =>
                                    setData(
                                        'calorie_intake',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.calorie_intake} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="recipe-status">Status</Label>
                            <Input
                                id="recipe-status"
                                value={recipe?.status_label ?? ''}
                                disabled
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Meal</Label>
                        <Select
                            value={data.meal}
                            onValueChange={(value) => setData('meal', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select meal" />
                            </SelectTrigger>
                            <SelectContent>
                                {meals.map((meal) => (
                                    <SelectItem
                                        key={meal.value}
                                        value={meal.value}
                                    >
                                        {meal.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.meal} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCloseEditRecipe}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
