import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { router } from '@inertiajs/react';
import { index } from '@/routes/diet';
import { view } from '@/routes/diet/generate';

export default function diet_page() {
    const onGenerateDietPlanClick = () => {
        router.get(view.url());
    };

    return (
        <div className="space-y-6 p-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <Heading
                    title="Diet"
                    description="Generate a personalized diet plan, track macros and liquid intake."
                />
            </div>

            <Card className="border-dashed">
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Start generation</CardTitle>

                        <CardDescription>
                            Click “Generate diet plan” to begin selecting your
                            macroelements, diet type, and daily calorie limit.
                        </CardDescription>
                    </div>

                    <Button type="button" onClick={onGenerateDietPlanClick}>
                        Generate diet plan
                    </Button>
                </CardHeader>
            </Card>
        </div>
    );
}

diet_page.layout = {
    breadcrumbs: [
        {
            title: 'Diet plan',
            href: index(),
        },
    ],
};
