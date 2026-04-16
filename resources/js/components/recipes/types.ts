export type EnumOption = {
    value: string;
    label: string;
};

export type RecipeListItem = {
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

export type RecipeFormData = {
    title: string;
    instructions: string;
    preparation_time: string;
    servings: number;
    difficulty: string;
    calorie_intake: number;
    diet_type: string;
    meal: string;
};

export const emptyRecipeForm: RecipeFormData = {
    title: '',
    instructions: '',
    preparation_time: '',
    servings: 1,
    difficulty: 'easy',
    calorie_intake: 0,
    diet_type: 'owned',
    meal: 'breakfast',
};

export function toRecipeForm(recipe: RecipeListItem): RecipeFormData {
    return {
        title: recipe.title,
        instructions: recipe.instructions,
        preparation_time: recipe.preparation_time,
        servings: recipe.servings,
        difficulty: recipe.difficulty,
        calorie_intake: recipe.calorie_intake,
        diet_type: recipe.diet_type,
        meal: recipe.meal,
    };
}
