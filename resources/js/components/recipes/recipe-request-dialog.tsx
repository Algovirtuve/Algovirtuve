import { useForm } from '@inertiajs/react';
import type {FormEvent} from 'react';
import InputError from '@/components/input-error';
import type { EnumOption } from '@/components/recipes/types';
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
import { request } from '@/routes/recipes';

type RecipeRequestForm = {
    title: string;
    instructions: string;
    preparation_time: string;
    servings: string;
    difficulty: string;
    calorie_intake: string;
    diet_type: string;
    meal: string;
};

const emptyRecipeRequestForm: RecipeRequestForm = {
    title: '',
    instructions: '',
    preparation_time: '',
    servings: '',
    difficulty: '',
    calorie_intake: '',
    diet_type: '',
    meal: '',
};

export function RecipeRequestDialog({
    isOpen,
    difficulties,
    dietTypes,
    meals,
    onCreateRequestCancel,
}: {
    isOpen: boolean;
    difficulties: EnumOption[];
    dietTypes: EnumOption[];
    meals: EnumOption[];
    onCreateRequestCancel: () => void;
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<RecipeRequestForm>(emptyRecipeRequestForm);

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            clearErrors();
            reset();
            onCreateRequestCancel();
        }
    };

    const onCreateRequest = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(request.url(), {
            preserveScroll: true,
            onSuccess: () => {
                clearErrors();
                reset();
                onCreateRequestCancel();
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create recipe request</DialogTitle>
                    <DialogDescription>
                        Fill in the recipe details and submit the request for
                        review.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onCreateRequest} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="request-title">Title</Label>
                        <Input
                            id="request-title"
                            value={data.title}
                            onChange={(event) =>
                                setData('title', event.target.value)
                            }
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="request-instructions">
                            Instructions
                        </Label>
                        <textarea
                            id="request-instructions"
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
                            <Label htmlFor="request-preparation-time">
                                Preparation time
                            </Label>
                            <Input
                                id="request-preparation-time"
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
                            <Label htmlFor="request-servings">Servings</Label>
                            <Input
                                id="request-servings"
                                type="number"
                                min={1}
                                value={data.servings}
                                onChange={(event) =>
                                    setData('servings', event.target.value)
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
                            <Label htmlFor="request-calorie-intake">
                                Calorie intake
                            </Label>
                            <Input
                                id="request-calorie-intake"
                                type="number"
                                min={0}
                                value={data.calorie_intake}
                                onChange={(event) =>
                                    setData(
                                        'calorie_intake',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.calorie_intake} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="request-status">Status</Label>
                            <Input
                                id="request-status"
                                value="Waiting for review"
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
                            onClick={onCreateRequestCancel}
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
