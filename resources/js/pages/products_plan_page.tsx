import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    generateProductsPlan,
    getRecipes,
    insertNewProduct,
    removeProduct,
    searchForProducts,
} from '@/actions/App/Http/Controllers/Shopping_managment/shopping_controller';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type RecipeListItem = {
    id: number;
    title: string;
};

type Shop = {
    title: string;
    address: string;
    city: string;
    price: number;
};

type GeneratedPlan = {
    id: number;
    generation_date: string;
    cheapest_products: Array<{
        store_product_id: number;
        product_id: number;
        title: string;
        quantity: number;
        measurement: string;
        price: number;
        store_title: string;
        store_city: string;
    }>;
    stores: Array<{
        id: number;
        title: string;
        address: string;
        city: string;
        cart_price: number;
        products: Array<{
            store_product_id: number;
            product_id: number;
            title: string;
            quantity: number;
            measurement: string;
            price: number;
        }>;
    }>;
};

export default function ProductsPlanPage({
    recipes,
    recipe_query,
    generated_plan,
    add_product,
}: {
    recipes: RecipeListItem[];
    recipe_query: string;
    generated_plan: GeneratedPlan | null;
    add_product: null | {
        product_title: string;
        shops: Shop[];
    };
}) {
    const [selectedRecipes, setSelectedRecipes] = useState<RecipeListItem[]>([]);
    const [isAddProductOpen, setIsAddProductOpen] = useState(false);
    const [productBeingRemoved, setProductBeingRemoved] = useState<null | {
        shoppingPlanId: number;
        storeProductId: number;
        title: string;
    }>(null);
    const [productTitle, setProductTitle] = useState('');

    const selectedRecipeIds = useMemo(
        () => selectedRecipes.map((recipe) => recipe.id),
        [selectedRecipes],
    );

    const cheapestProducts = useMemo(
        () => (generated_plan?.cheapest_products ?? []),
        [generated_plan],
    );

    const showRecipeList = recipes;

    const onChange = (value: string, context: 'recipes' | 'products') => {
        if (context === 'recipes') {
            router.get(
                getRecipes().url,
                {
                    query: value,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );

            return;
        }

        setProductTitle(value);

        if (!generated_plan) {
            return;
        }

        router.post(
            searchForProducts().url,
            {
                product_title: value,
                generated_plan: generated_plan
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['add_product'],
            },
        );
    };

    const insertToTemplateList = (recipe: RecipeListItem) => {
        setSelectedRecipes((previous) => {
            if (previous.some((item) => item.id === recipe.id)) {
                return previous;
            }

            return [...previous, recipe];
        });
    };

    const onChooseRecipe = (recipe: RecipeListItem) => {
        insertToTemplateList(recipe);
    };

    const onGeneratePlan = () => {
        router.post(
            generateProductsPlan().url,
            {
                recipe_ids: selectedRecipeIds,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const onAddProduct = () => {
        setIsAddProductOpen(true);
        setProductTitle(add_product?.product_title ?? '');
    };

    const onAddProductOpenChange = (open: boolean) => {
        setIsAddProductOpen(open);
        if (!open) {
            setProductTitle('');
        }
    };

    const onSelectedShop = (shop: Shop) => {
        if (!generated_plan) {
            return;
        }

        router.patch(
            insertNewProduct(generated_plan.id).url,
            {
                product_title: productTitle,
                store_title: shop.title,
                address: shop.address,
                city: shop.city,
                price: shop.price,
                quantity: 1,
                measurement: 'unit',
                generated_plan: generated_plan,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setIsAddProductOpen(false);
                    setProductTitle('');
                },
            },
        );
    };

    const onRemoveProduct = (storeProductId: number, title: string) => {
        if (!generated_plan) {
            return;
        }

        setProductBeingRemoved({
            shoppingPlanId: generated_plan.id,
            storeProductId,
            title,
        });
    };

    const onConfirmRemove = () => {
        if (!productBeingRemoved) {
            return;
        }

        router.patch(
            removeProduct(
                {
                    shoppingPlan: productBeingRemoved.shoppingPlanId,
                    storeProduct: productBeingRemoved.storeProductId,
                },
            ).url,
            {
                generated_plan: generated_plan,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setProductBeingRemoved(null);
                },
            },
        );
    };

    const onCancelRemove = () => {
        setProductBeingRemoved(null);
    };

    return (
        <>
            <Head title="Generate shopping plan" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Generate shopping plan"
                    description="Search recipes, select them, and generate a shopping plan."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Recipe search</CardTitle>
                        <CardDescription>
                            Start typing a recipe title to load results.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex gap-3">
                            <Input
                                value={recipe_query}
                                onChange={(event) =>
                                    onChange(event.target.value, 'recipes')
                                }
                                placeholder="Recipe title"
                            />
                        </div>

                        {showRecipeList.length === 0 ? (
                            <div className="text-sm text-muted-foreground">
                                No recipes found.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {showRecipeList.map((recipe) => (
                                    <div
                                        key={recipe.id}
                                        className="flex items-center justify-between gap-3 rounded-md border p-3"
                                    >
                                        <div className="text-sm font-medium">
                                            {recipe.title}
                                        </div>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => onChooseRecipe(recipe)}
                                        >
                                            Add
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Selected recipes</CardTitle>
                        <CardDescription>
                            These recipes will be used to generate the plan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {selectedRecipes.length === 0 ? (
                            <div className="text-sm text-muted-foreground">
                                No selected recipes yet.
                            </div>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {selectedRecipes.map((recipe) => (
                                    <div
                                        key={recipe.id}
                                        className="rounded-md border px-3 py-2 text-sm"
                                    >
                                        {recipe.title}
                                    </div>
                                ))}
                            </div>
                        )}

                        <Button
                            type="button"
                            onClick={onGeneratePlan}
                            disabled={selectedRecipeIds.length === 0}
                        >
                            Generate plan
                        </Button>
                    </CardContent>
                </Card>

                {generated_plan ? (
                    <Card>
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <CardTitle>Generated plan</CardTitle>
                                <CardDescription>
                                    Generated on {generated_plan.generation_date}
                                </CardDescription>
                            </div>

                            <Button type="button" onClick={onAddProduct}>
                                Add product
                            </Button>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            {generated_plan.stores.length === 0 ? (
                                <div className="text-sm text-muted-foreground">
                                    No stores/products in this plan.
                                </div>
                            ) : (
                                <>
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                Products (cheapest shop)
                                            </CardTitle>
                                            <CardDescription>
                                                Full list of products and where they are cheapest.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            {cheapestProducts.length === 0 ? (
                                                <div className="text-sm text-muted-foreground">
                                                    No products.
                                                </div>
                                            ) : (
                                                cheapestProducts.map((product) => (
                                                    <div
                                                        key={product.product_id}
                                                        className="flex items-center justify-between gap-3 rounded-md border p-3"
                                                    >
                                                        <div className="space-y-1">
                                                            <div className="text-sm font-medium">
                                                                {product.title}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {product.quantity} {product.measurement} · {product.price.toFixed(2)}
                                                            </div>
                                                        </div>

                                                        <div className="text-right text-xs text-muted-foreground">
                                                            {product.store_title}
                                                            <div>{product.store_city}</div>
                                                        </div>
                                                    </div>
                                                ))
                                            )}
                                        </CardContent>
                                    </Card>

                                    {generated_plan.stores.map((store) => (
                                        <Card key={store.id}>
                                            <CardHeader>
                                                <CardTitle className="text-base">
                                                    {store.title}
                                                </CardTitle>
                                                <CardDescription>
                                                    {store.address} · {store.city} · Total: {store.cart_price.toFixed(2)}
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="space-y-2">
                                                {store.products.length === 0 ? (
                                                    <div className="text-sm text-muted-foreground">
                                                        No products.
                                                    </div>
                                                ) : (
                                                    store.products.map((product) => (
                                                        <div
                                                            key={`${store.id}-${product.product_id}`}
                                                            className="flex items-center justify-between gap-3 rounded-md border p-3"
                                                        >
                                                            <div className="space-y-1">
                                                                <div className="text-sm font-medium">
                                                                    {product.title}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {product.quantity} {product.measurement} · {product.price.toFixed(2)}
                                                                </div>
                                                            </div>

                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    onRemoveProduct(
                                                                        product.store_product_id,
                                                                        product.title,
                                                                    )
                                                                }
                                                            >
                                                                Remove
                                                            </Button>
                                                        </div>
                                                    ))
                                                )}
                                            </CardContent>
                                        </Card>
                                    ))}
                                </>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle>No plan generated yet</CardTitle>
                            <CardDescription>
                                Generate a plan to see missing products and start editing.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </div>

            <Dialog open={isAddProductOpen} onOpenChange={onAddProductOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add product</DialogTitle>
                        <DialogDescription>
                            Type a product name to see shops, then select a shop.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <Input
                            value={productTitle}
                            onChange={(event) =>
                                onChange(event.target.value, 'products')
                            }
                            placeholder="Product title"
                        />

                        {add_product?.shops?.length ? (
                            <div className="space-y-2">
                                {add_product.shops.map((shop) => (
                                    <div
                                        key={`${shop.title}-${shop.address}`}
                                        className="flex items-center justify-between gap-3 rounded-md border p-3"
                                    >
                                        <div className="space-y-1">
                                            <div className="text-sm font-medium">
                                                {shop.title}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {shop.address} · {shop.city} · {shop.price.toFixed(2)}
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => onSelectedShop(shop)}
                                        >
                                            Select
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-sm text-muted-foreground">
                                {productTitle.trim()
                                    ? 'No shops yet.'
                                    : 'Start typing to search.'}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsAddProductOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={productBeingRemoved !== null}
                onOpenChange={(open) => !open && onCancelRemove()}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove product</DialogTitle>
                        <DialogDescription>
                            {productBeingRemoved
                                ? `Remove "${productBeingRemoved.title}" from the plan?`
                                : 'Remove this product?'}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancelRemove}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={onConfirmRemove}>
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductsPlanPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Shopping plan',
            href: '#',
        },
    ],
};
