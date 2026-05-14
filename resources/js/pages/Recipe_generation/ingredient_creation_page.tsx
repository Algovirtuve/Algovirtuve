import { Head, Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as onIngredientPage,
    store as onIngredientStore,
} from '@/routes/ingredients';

type IngredientCategoryOption = {
    value: string;
    label: string;
};

export default function IngredientCreationPage({
    ingredient_categories,
}: {
    ingredient_categories: IngredientCategoryOption[];
}) {
    const { errors } = usePage<{ errors?: Record<string, string[]> }>().props;

    return (
        <>
            <Head title="Create ingredient" />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm tracking-[0.24em] text-muted-foreground uppercase">
                        Ingredients
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Create a new ingredient
                    </h1>
                </div>
                <TextLink href={onIngredientPage()}>
                    <ArrowLeft />
                    Back to ingredients
                </TextLink>
            </div>

            <Form
                {...onIngredientStore.form()}
                className="grid max-w-2xl gap-6"
            >
                {({ processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="category">
                                Ingredient category
                            </Label>
                            <Select name="category" defaultValue="">
                                <SelectTrigger id="category">
                                    <SelectValue placeholder="Choose an ingredient category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ingredient_categories.map((category) => (
                                        <SelectItem
                                            key={category.value}
                                            value={category.value}
                                        >
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors?.category?.[0]} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                className="min-w-[10rem]"
                                disabled={processing}
                                type="submit"
                            >
                                <Plus />
                                Create ingredient
                            </Button>
                            <Link
                                href={onIngredientPage()}
                                className="text-sm text-muted-foreground hover:text-primary"
                            >
                                Cancel
                            </Link>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
