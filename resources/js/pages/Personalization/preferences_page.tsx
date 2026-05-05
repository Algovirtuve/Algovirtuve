import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, destroy, index } from '@/routes/preferences';

type PreferenceItem = {
    id: number;
    preference_status: 'liked' | 'disliked' | 'awaiting';
    status_label: string;
    generation_date: string | null;
    recipe: {
        id: number;
        title: string;
        instructions: string;
        preparation_time: string;
        servings: number;
        difficulty: string;
        difficulty_label: string;
        calorie_intake: number;
        status: string;
        status_label: string;
        diet_type: string;
        diet_type_label: string;
        meal: string;
        meal_label: string;
    };
};

export default function PreferencesIndex({
    preferences,
}: {
    preferences: PreferenceItem[];
}) {
    return (
        <>
            <Head title="Preferences" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        title="Recipe preferences"
                        description="Add liked or disliked recipes and manage them from one place."
                    />

                    <Button asChild>
                        <Link href={create()} prefetch>
                            Add preference
                        </Link>
                    </Button>
                </div>

                {preferences.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle>No preferences yet</CardTitle>
                            <CardDescription>
                                Start by adding a recipe you like or dislike.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter>
                            <Button asChild>
                                <Link href={create()} prefetch>
                                    Add your first preference
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {preferences.map((preference) => (
                            <Card
                                key={preference.id}
                                className="justify-between"
                            >
                                <CardHeader className="gap-3">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <CardTitle>
                                                {preference.recipe.title}
                                            </CardTitle>
                                            <CardDescription className="line-clamp-3 max-w-xl">
                                                {preference.recipe.instructions}
                                            </CardDescription>
                                        </div>

                                        <Badge
                                            variant={
                                                preference.preference_status ===
                                                'liked'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {preference.status_label}
                                        </Badge>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4">
                                    <div className="flex flex-wrap gap-2 text-xs">
                                        <Badge variant="outline">
                                            {preference.recipe.difficulty_label}
                                        </Badge>
                                        <Badge variant="outline">
                                            {preference.recipe.preparation_time}
                                        </Badge>
                                        <Badge variant="outline">
                                            {preference.recipe.servings}{' '}
                                            servings
                                        </Badge>
                                        <Badge variant="outline">
                                            {preference.recipe.calorie_intake}{' '}
                                            kcal
                                        </Badge>
                                        <Badge variant="outline">
                                            {preference.recipe.diet_type_label}
                                        </Badge>
                                        <Badge variant="outline">
                                            {preference.recipe.meal_label}
                                        </Badge>
                                    </div>
                                </CardContent>

                                <CardFooter className="justify-between gap-3 border-t pt-6">
                                    <div className="space-y-1 text-sm text-muted-foreground">
                                        <p>
                                            Recipe status:{' '}
                                            {preference.recipe.status_label}
                                        </p>
                                        <p>
                                            Generated:{' '}
                                            {preference.generation_date ??
                                                'N/A'}
                                        </p>
                                    </div>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={destroy(preference.id)}
                                            as="button"
                                        >
                                            Delete
                                        </Link>
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

PreferencesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Preferences',
            href: index(),
        },
    ],
};
