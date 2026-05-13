import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/diet';

type SelectedMacroelement = { id: number; target_kcal: number };

type GeneratedPlan = {
    id: number;
    diet_type: string;
    day_calorie_limit: number;
    meal_calorie_limits: {
        breakfast: number;
        lunch: number;
        dinner: number;
    };
    selected_macroelements: SelectedMacroelement[];
};

type Recipe = {
    id: number;
    title: string;
    calorie_intake: number;
    meal_label: string;
    diet_type_label: string;
};

type Recommendation = {
    score: number;
    recipe: Recipe;
};

export default function diet_plan_page({
    generatedPlan,
    recommendations,
}: {
    generatedPlan: GeneratedPlan;
    recommendations: Record<'breakfast' | 'lunch' | 'dinner', Recommendation[]>;
}) {
    const renderMeal = (meal: 'breakfast' | 'lunch' | 'dinner') => {
        const list = recommendations[meal] ?? [];

        return (
            <Card>
                <CardHeader>
                    <CardTitle className="capitalize">{meal}</CardTitle>
                    <CardDescription>
                        Top 3 recipes (most suitable first).
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {list.length === 0 ? (
                        <div className="text-sm text-muted-foreground">
                            No recipes found for this meal.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {list.map((item) => (
                                <div
                                    key={item.recipe.id}
                                    className="rounded-md border p-3"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-medium">
                                                {item.recipe.title}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {item.recipe.calorie_intake}{' '}
                                                kcal
                                            </div>
                                        </div>
                                        <Badge variant="secondary">
                                            Score {item.score}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        );
    };

    return (
        <div className="space-y-6 p-4">
            <Heading
                title="Diet plan"
                description="Generated plan and meal recommendations."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Chosen data</CardTitle>
                    <CardDescription>
                        Diet type, daily calorie limit, and split per meal.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-2">
                    <div className="text-sm">
                        <span className="font-medium">Diet type:</span>{' '}
                        {generatedPlan.diet_type}
                    </div>
                    <div className="text-sm">
                        <span className="font-medium">Day kcal:</span>{' '}
                        {generatedPlan.day_calorie_limit}
                    </div>
                    <div className="text-sm">
                        <span className="font-medium">Meal limits:</span>{' '}
                        Breakfast {generatedPlan.meal_calorie_limits.breakfast},
                        Lunch {generatedPlan.meal_calorie_limits.lunch}, Dinner{' '}
                        {generatedPlan.meal_calorie_limits.dinner}
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-3">
                {renderMeal('breakfast')}
                {renderMeal('lunch')}
                {renderMeal('dinner')}
            </div>
        </div>
    );
}

diet_plan_page.layout = {
    breadcrumbs: [
        {
            title: 'Diet plan',
            href: index(),
        },
        {
            title: 'Generated',
            href: index(),
        },
    ],
};
