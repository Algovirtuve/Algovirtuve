import { Pencil, Trash2 } from 'lucide-react';
import type { RecipeListItem } from '@/components/recipes/types';
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

export function RecipeCard({
    recipe,
    onEdit,
    onDelete,
}: {
    recipe: RecipeListItem;
    onEdit: (recipe: RecipeListItem) => void;
    onDelete: (recipe: RecipeListItem) => void;
}) {
    return (
        <Card className="justify-between">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>{recipe.title}</CardTitle>
                        <CardDescription className="line-clamp-3 max-w-xl">
                            {recipe.instructions}
                        </CardDescription>
                    </div>

                    <Badge variant="outline">{recipe.status_label}</Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="flex flex-wrap gap-2 text-xs">
                    <Badge variant="outline">{recipe.difficulty_label}</Badge>
                    <Badge variant="outline">{recipe.preparation_time}</Badge>
                    <Badge variant="outline">{recipe.servings} servings</Badge>
                    <Badge variant="outline">
                        {recipe.calorie_intake} kcal
                    </Badge>
                    <Badge variant="outline">{recipe.diet_type_label}</Badge>
                    <Badge variant="outline">{recipe.meal_label}</Badge>
                </div>
            </CardContent>

            <CardFooter className="flex flex-wrap justify-end gap-3 border-t pt-6">
                <Button variant="outline" onClick={() => onEdit(recipe)}>
                    <Pencil />
                    Edit
                </Button>

                <Button variant="destructive" onClick={() => onDelete(recipe)}>
                    <Trash2 />
                    Delete
                </Button>
            </CardFooter>
        </Card>
    );
}
