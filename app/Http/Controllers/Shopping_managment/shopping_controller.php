<?php

namespace App\Http\Controllers\Shopping_managment;

use App\Enums\Measurement;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateProductsPlanRequest;
use App\Http\Requests\GetRecipesRequest;
use App\Http\Requests\InsertNewProductRequest;
use App\Http\Requests\RemoveProductRequest;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\ShoppingCart;
use App\Models\ShoppingPlan;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\products_service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class shopping_controller extends Controller
{
    public function render(Request $request): Response
    {
        return $this->renderProductsPlanPage($request);
    }

    public function getRecipes(GetRecipesRequest $request): Response
    {
        $query = $request->recipeQuery();

        $recipes = Recipe::query()
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', '%'.$query.'%'))
            ->orderBy('title')
            ->limit(20)
            ->get()
            ->map(static fn (Recipe $recipe): array => [
                'id' => $recipe->id,
                'title' => $recipe->title,
            ])
            ->values()
            ->all();

        return $this->renderProductsPlanPage($request, [
            'recipes' => $recipes,
            'recipe_query' => $query,
        ]);
    }

    public function generateProductsPlan(GenerateProductsPlanRequest $request, products_service $productsService): Response
    {
        /** @var User $user */
        $user = $request->user();

        $recipes = Recipe::query()
            ->whereKey($request->recipeIds())
            ->with(['ingredients.product'])
            ->get();

        $missingIngredients = $this->checkMissingIngredients($user->ingredients()->with('product')->get(), $recipes);

        if ($missingIngredients === []) {
            return $this->renderProductsPlanPage($request, [
                'generated_plan' => null,
                'flash' => [
                    'toast' => [
                        'type' => 'info',
                        'message' => 'There are no missing ingredients.',
                    ],
                ],
            ]);
        }

        $productsFromApi = $productsService->getProducts(
            collect($missingIngredients)
                ->map(static fn (array $missing): array => [
                    'title' => $missing['title'],
                    'quantity' => $missing['quantity'],
                    'measurement' => $missing['measurement'],
                ])
                ->values()
                ->all(),
        );

        $generatedPlan = DB::transaction(function () use ($user, $missingIngredients, $productsFromApi, $productsService): array {
            $storesFromApi = collect($productsFromApi)
                ->flatMap(static fn (array $product): array => is_array($product['stores'] ?? null) ? $product['stores'] : [])
                ->values()
                ->all();

            $storeByTitle = $this->checkShops($storesFromApi);

            $shoppingPlan = insert($user->shoppingPlans(), [
                'generation_date' => now()->toDateString(),
            ]);

            $storeProducts = [];

            foreach ($missingIngredients as $missingIndex => $missing) {
                $apiProduct = $productsFromApi[$missingIndex] ?? null;

                if (! is_array($apiProduct)) {
                    continue;
                }

                $stores = Arr::where($apiProduct['stores'] ?? [], static fn (mixed $store): bool => is_array($store) && isset($store['title'], $store['price']));

                if ($stores === []) {
                    continue;
                }

                $selectedStore = collect($stores)->sortBy('price')->first();

                if (! is_array($selectedStore)) {
                    continue;
                }

                $storeTitle = (string) ($selectedStore['title'] ?? '');
                $store = $storeByTitle[$storeTitle] ?? null;

                if (! $store instanceof Store) {
                    continue;
                }

                $product = $this->resolveOrCreateIngredientProduct($missing);

                $storeProducts[] = insert(StoreProduct::class, [
                    'store_id' => $store->id,
                    'shopping_plan_id' => $shoppingPlan->id,
                    'product_id' => $product->id,
                    'price' => (float) ($selectedStore['price'] ?? 0),
                    'quantity' => (int) ($missing['quantity'] ?? 1),
                ]);
            }

            $shoppingPlan = $this->loadShoppingPlan($shoppingPlan);

            $pricing = $this->buildPlanPricing($shoppingPlan, $productsService);
            $this->recalculateShoppingCartsFromPricing($shoppingPlan, $pricing);

            $shoppingPlan = $this->loadShoppingPlan($shoppingPlan);

            return $this->transformGeneratedPlan($shoppingPlan, $pricing);
        });

        return $this->renderProductsPlanPage($request, [
            'generated_plan' => $generatedPlan,
        ]);
    }

    public function insertNewProduct(InsertNewProductRequest $request, ShoppingPlan $shoppingPlan, products_service $productsService): Response
    {
        $this->authorizeShoppingPlan($request, $shoppingPlan);

        if (! $request->hasSelectedShop()) {
            $shops = $productsService->getShops($request->productTitle());

            if (! $this->validate($shops)) {
                return $this->renderProductsPlanPage($request, [
                    'generated_plan' => $this->transformGeneratedPlan(
                        $this->loadShoppingPlan($shoppingPlan),
                        $this->buildPlanPricing($this->loadShoppingPlan($shoppingPlan), $productsService),
                    ),
                    'add_product' => [
                        'product_title' => $request->productTitle(),
                        'shops' => [],
                    ],
                    'flash' => [
                        'toast' => [
                            'type' => 'error',
                            'message' => 'No shops found for this product.',
                        ],
                    ],
                ]);
            }

            return $this->renderProductsPlanPage($request, [
                'generated_plan' => $this->transformGeneratedPlan(
                    $this->loadShoppingPlan($shoppingPlan),
                    $this->buildPlanPricing($this->loadShoppingPlan($shoppingPlan), $productsService),
                ),
                'add_product' => [
                    'product_title' => $request->productTitle(),
                    'shops' => $shops,
                ],
            ]);
        }

        $result = DB::transaction(function () use ($request, $shoppingPlan): array {
            $shop = $request->selectedShop();
            $store = $this->checkShop($shop);

            $product = $this->resolveOrCreateFreeformProduct($request->productTitle(), $request->selectedProduct());

            insert(StoreProduct::class, [
                'store_id' => $store->id,
                'shopping_plan_id' => $shoppingPlan->id,
                'product_id' => $product->id,
                'price' => $request->selectedProduct()['price'],
                'quantity' => $request->selectedProduct()['quantity'],
            ]);

            $shoppingPlan->update([
                'generation_date' => now()->toDateString(),
            ]);

            return [
                'type' => 'success',
                'message' => 'Product added successfully.',
            ];
        });

        $shoppingPlan = $this->loadShoppingPlan($shoppingPlan);
        $pricing = $this->buildPlanPricing($shoppingPlan, $productsService);
        $this->recalculateShoppingCartsFromPricing($shoppingPlan, $pricing);
        $shoppingPlan = $this->loadShoppingPlan($shoppingPlan);
        $pricing = $this->buildPlanPricing($shoppingPlan, $productsService);

        return $this->renderProductsPlanPage($request, [
            'generated_plan' => $this->transformGeneratedPlan($shoppingPlan, $pricing),
            'add_product' => null,
            'flash' => [
                'toast' => $result,
            ],
        ]);
    }

    public function removeProduct(RemoveProductRequest $request, ShoppingPlan $shoppingPlan, StoreProduct $storeProduct, products_service $productsService): Response
    {
        $this->authorizeShoppingPlan($request, $shoppingPlan);

        if ((int) $storeProduct->shopping_plan_id !== (int) $shoppingPlan->id) {
            abort(404);
        }

        DB::transaction(function () use ($storeProduct): void {
            $storeProduct->delete();
        });

        $shoppingPlan = $this->loadShoppingPlan($shoppingPlan);
        $pricing = $this->buildPlanPricing($shoppingPlan, $productsService);
        $this->recalculateShoppingCartsFromPricing($shoppingPlan, $pricing);
        $shoppingPlan = $this->loadShoppingPlan($shoppingPlan);
        $pricing = $this->buildPlanPricing($shoppingPlan, $productsService);

        return $this->renderProductsPlanPage($request, [
            'generated_plan' => $this->transformGeneratedPlan($shoppingPlan, $pricing),
            'flash' => [
                'toast' => [
                    'type' => 'success',
                    'message' => 'Product removed successfully.',
                ],
            ],
        ]);
    }

    /**
     * @param  Collection<int, Ingredient>  $userIngredients
     * @param  Collection<int, Recipe>  $recipes
     * @return list<array{ingredient_id: int, title: string, quantity: int, measurement: string}>
     */
    private function checkMissingIngredients(EloquentCollection $userIngredients, EloquentCollection $recipes): array
    {
        $availableByIngredientId = $userIngredients
            ->mapWithKeys(static fn ($ingredient): array => [
                $ingredient->id => (int) ($ingredient->pivot?->quantity ?? 0),
            ]);

        $required = collect();

        foreach ($recipes as $recipe) {
            foreach ($recipe->ingredients as $ingredient) {
                $required->push([
                    'ingredient_id' => (int) $ingredient->id,
                    'title' => (string) ($ingredient->product?->title ?? $ingredient->category->value),
                    'quantity' => (int) ($ingredient->pivot?->quantity ?? 1),
                    'measurement' => (string) ($ingredient->pivot?->measurement ?? Measurement::UNIT->value),
                ]);
            }
        }

        $requiredSummed = $required
            ->groupBy(fn (array $row): string => $row['ingredient_id'].'|'.$row['measurement'])
            ->map(function (SupportCollection $rows): array {
                $first = $rows->first();

                return [
                    'ingredient_id' => (int) $first['ingredient_id'],
                    'title' => (string) $first['title'],
                    'quantity' => (int) $rows->sum('quantity'),
                    'measurement' => (string) $first['measurement'],
                ];
            })
            ->values();

        return $requiredSummed
            ->filter(function (array $requiredRow) use ($availableByIngredientId): bool {
                $available = (int) ($availableByIngredientId[$requiredRow['ingredient_id']] ?? 0);

                return $available < (int) $requiredRow['quantity'];
            })
            ->map(function (array $requiredRow) use ($availableByIngredientId): array {
                $available = (int) ($availableByIngredientId[$requiredRow['ingredient_id']] ?? 0);

                return [
                    ...$requiredRow,
                    'quantity' => max(1, (int) $requiredRow['quantity'] - $available),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{title?: string, address?: string, city?: string}>  $shops
     * @return array<string, Store>
     */
    private function checkShops(array $shops): array
    {
        $storeByTitle = [];

        foreach ($shops as $shop) {
            $title = (string) ($shop['title'] ?? '');

            if ($title === '' || isset($storeByTitle[$title])) {
                continue;
            }

            $storeByTitle[$title] = $this->checkShop([
                'title' => $title,
                'address' => (string) ($shop['address'] ?? ''),
                'city' => (string) ($shop['city'] ?? ''),
            ]);
        }

        return $storeByTitle;
    }

    /**
     * @param  array{title: string, address: string, city: string}  $shop
     */
    private function checkShop(array $shop): Store
    {
        $title = trim((string) $shop['title']);

        $existing = Store::query()->where('title', $title)->first();

        if ($existing instanceof Store) {
            return $existing;
        }

        return insert(Store::class, [
            'title' => $title,
            'address' => trim((string) $shop['address']),
            'city' => trim((string) $shop['city']),
        ]);
    }

    /**
     * @param  list<array{title?: string, address?: string, city?: string, price?: float|int}>  $shops
     */
    private function validate(array $shops): bool
    {
        if ($shops === []) {
            return false;
        }

        foreach ($shops as $shop) {
            if (! is_array($shop)) {
                return false;
            }

            if (! isset($shop['title'], $shop['address'], $shop['city'], $shop['price'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{ingredient_id: int, title: string, quantity: int, measurement: string}  $missing
     */
    private function resolveOrCreateIngredientProduct(array $missing): Product
    {
        $ingredientId = (int) $missing['ingredient_id'];

        $existing = Product::query()->where('ingredient_id', $ingredientId)->first();

        if ($existing instanceof Product) {
            $existing->update([
                'title' => (string) $missing['title'],
                'quantity' => (int) $missing['quantity'],
                'measurement' => (string) $missing['measurement'],
            ]);

            return $existing;
        }

        return insert(Product::class, [
            'title' => (string) $missing['title'],
            'quantity' => (int) $missing['quantity'],
            'measurement' => (string) $missing['measurement'],
            'ingredient_id' => $ingredientId,
        ]);
    }

    /**
     * @param  array{price: float, quantity: int, measurement: string}  $productData
     */
    private function resolveOrCreateFreeformProduct(string $title, array $productData): Product
    {
        $existing = Product::query()
            ->where('title', $title)
            ->whereNull('ingredient_id')
            ->first();

        if ($existing instanceof Product) {
            $existing->update([
                'quantity' => (int) $productData['quantity'],
                'measurement' => (string) $productData['measurement'],
            ]);

            return $existing;
        }

        return insert(Product::class, [
            'title' => $title,
            'quantity' => (int) $productData['quantity'],
            'measurement' => (string) $productData['measurement'],
            'ingredient_id' => null,
            'tool_id' => null,
        ]);
    }

    private function authorizeShoppingPlan(Request $request, ShoppingPlan $shoppingPlan): void
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $shoppingPlan->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function loadShoppingPlan(ShoppingPlan $shoppingPlan): ShoppingPlan
    {
        return ShoppingPlan::query()
            ->whereKey($shoppingPlan->id)
            ->with(['shoppingCarts.store', 'storeProducts.store', 'storeProducts.product'])
            ->firstOrFail();
    }

    /**
     * Builds a deterministic pricing snapshot for all plan products across all shops.
     *
     * @return array{
     *     store_by_title: array<string, Store>,
     *     prices_by_product_id: array<int, array<string, float>>,
     *     cheapest_by_product_id: array<int, array{store_title: string, price: float}>,
     *     full_basket_store_titles: list<string>
     * }
     */
    private function buildPlanPricing(ShoppingPlan $shoppingPlan, products_service $productsService): array
    {
        $shoppingPlan->loadMissing(['storeProducts.product']);

        $storeProducts = $shoppingPlan->storeProducts->values();

        $productsRequest = $storeProducts
            ->map(static fn (StoreProduct $storeProduct): array => [
                'title' => (string) ($storeProduct->product?->title ?? 'Product'),
                'quantity' => (int) $storeProduct->quantity,
                'measurement' => (string) ($storeProduct->product?->measurement?->value ?? Measurement::UNIT->value),
            ])
            ->values()
            ->all();

        $productsFromApi = $productsService->getProducts($productsRequest);

        $storesFromApi = collect($productsFromApi)
            ->flatMap(static fn (array $product): array => is_array($product['stores'] ?? null) ? $product['stores'] : [])
            ->values()
            ->all();

        $storeByTitle = $this->checkShops($storesFromApi);

        $pricesByProductId = [];
        $cheapestByProductId = [];
        $fullBasketStoreTitles = null;

        foreach ($storeProducts as $index => $storeProduct) {
            $apiProduct = $productsFromApi[$index] ?? null;

            $stores = is_array($apiProduct)
                ? Arr::where($apiProduct['stores'] ?? [], static fn (mixed $store): bool => is_array($store) && isset($store['title'], $store['price']))
                : [];

            $titles = collect($stores)
                ->map(static fn (array $storeRow): string => (string) ($storeRow['title'] ?? ''))
                ->filter(static fn (string $title): bool => $title !== '')
                ->unique()
                ->values()
                ->all();

            $fullBasketStoreTitles = $fullBasketStoreTitles === null
                ? $titles
                : array_values(array_intersect($fullBasketStoreTitles, $titles));

            $pricesByProductId[(int) $storeProduct->product_id] = collect($stores)
                ->mapWithKeys(static fn (array $storeRow): array => [
                    (string) $storeRow['title'] => (float) $storeRow['price'],
                ])
                ->all();

            $selectedStore = collect($stores)->sortBy('price')->first();
            $cheapestByProductId[(int) $storeProduct->product_id] = [
                'store_title' => (string) ($selectedStore['title'] ?? ''),
                'price' => (float) ($selectedStore['price'] ?? 0),
            ];
        }

        return [
            'store_by_title' => $storeByTitle,
            'prices_by_product_id' => $pricesByProductId,
            'cheapest_by_product_id' => $cheapestByProductId,
            'full_basket_store_titles' => array_values($fullBasketStoreTitles ?? []),
        ];
    }

    /**
     * @param  array{
     *     store_by_title: array<string, Store>,
     *     prices_by_product_id: array<int, array<string, float>>,
     *     cheapest_by_product_id: array<int, array{store_title: string, price: float}>,
     *     full_basket_store_titles: list<string>
     * }  $pricing
     */
    private function recalculateShoppingCartsFromPricing(ShoppingPlan $shoppingPlan, array $pricing): void
    {
        $shoppingPlan->loadMissing(['storeProducts']);

        ShoppingCart::query()
            ->where('shopping_plan_id', $shoppingPlan->id)
            ->delete();

        if ($shoppingPlan->storeProducts->isEmpty()) {
            return;
        }

        $storeTitles = $pricing['full_basket_store_titles'] ?? [];

        if (! is_array($storeTitles) || $storeTitles === []) {
            return;
        }

        foreach ($storeTitles as $storeTitle) {
            $storeTitle = (string) $storeTitle;
            $store = $pricing['store_by_title'][$storeTitle] ?? null;

            if (! $store instanceof Store) {
                continue;
            }

            $total = (float) $shoppingPlan->storeProducts
                ->sum(function (StoreProduct $storeProduct) use ($pricing, $storeTitle): float {
                    $productId = (int) $storeProduct->product_id;
                    $unitPrice = (float) ($pricing['prices_by_product_id'][$productId][$storeTitle] ?? 0);

                    return $unitPrice * (int) $storeProduct->quantity;
                });

            insert(ShoppingCart::class, [
                'price' => $total,
                'store_id' => $store->id,
                'shopping_plan_id' => $shoppingPlan->id,
            ]);
        }
    }

    /**
     * @param  array{
     *     store_by_title: array<string, Store>,
     *     prices_by_product_id: array<int, array<string, float>>,
     *     cheapest_by_product_id: array<int, array{store_title: string, price: float}>,
     *     full_basket_store_titles: list<string>
     * }  $pricing
     * @return array{
     *     id: int,
     *     generation_date: string,
     *     cheapest_products: list<array{
     *         store_product_id: int,
     *         product_id: int,
     *         title: string,
     *         quantity: int,
     *         measurement: string,
     *         price: float,
     *         store_title: string,
     *         store_city: string
     *     }>,
     *     stores: list<array{
     *         id: int,
     *         title: string,
     *         address: string,
     *         city: string,
     *         cart_price: float,
     *         products: list<array{
     *             store_product_id: int,
     *             product_id: int,
     *             title: string,
     *             quantity: int,
     *             measurement: string,
     *             price: float
     *         }>
     *     }>
     * }
     */
    private function transformGeneratedPlan(ShoppingPlan $shoppingPlan, array $pricing): array
    {
        $shoppingPlan->loadMissing(['shoppingCarts.store', 'storeProducts.product']);

        $storeProducts = $shoppingPlan->storeProducts->values();

        $cheapestProducts = $storeProducts
            ->map(function (StoreProduct $storeProduct) use ($pricing): array {
                $productId = (int) $storeProduct->product_id;
                $cheapest = $pricing['cheapest_by_product_id'][$productId] ?? ['store_title' => '', 'price' => 0];

                $storeTitle = (string) ($cheapest['store_title'] ?? '');
                $store = $pricing['store_by_title'][$storeTitle] ?? null;

                return [
                    'store_product_id' => (int) $storeProduct->id,
                    'product_id' => $productId,
                    'title' => (string) ($storeProduct->product?->title ?? 'Product'),
                    'quantity' => (int) $storeProduct->quantity,
                    'measurement' => (string) ($storeProduct->product?->measurement?->value ?? Measurement::UNIT->value),
                    'price' => (float) ($cheapest['price'] ?? 0),
                    'store_title' => $store instanceof Store ? (string) $store->title : $storeTitle,
                    'store_city' => $store instanceof Store ? (string) $store->city : '',
                ];
            })
            ->values()
            ->all();

        $stores = $shoppingPlan->shoppingCarts
            ->sortBy('price')
            ->map(function (ShoppingCart $cart) use ($storeProducts, $pricing): array {
                /** @var Store $store */
                $store = $cart->store;
                $storeTitle = (string) $store->title;

                $products = $storeProducts
                    ->map(function (StoreProduct $storeProduct) use ($pricing, $storeTitle): array {
                        $productId = (int) $storeProduct->product_id;
                        $unitPrice = (float) ($pricing['prices_by_product_id'][$productId][$storeTitle] ?? 0);

                        return [
                            'store_product_id' => (int) $storeProduct->id,
                            'product_id' => $productId,
                            'title' => (string) ($storeProduct->product?->title ?? 'Product'),
                            'quantity' => (int) $storeProduct->quantity,
                            'measurement' => (string) ($storeProduct->product?->measurement?->value ?? Measurement::UNIT->value),
                            'price' => $unitPrice,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => (int) $store->id,
                    'title' => (string) $store->title,
                    'address' => (string) $store->address,
                    'city' => (string) $store->city,
                    'cart_price' => (float) $cart->price,
                    'products' => $products,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $shoppingPlan->id,
            'generation_date' => (string) $shoppingPlan->generation_date->toDateString(),
            'cheapest_products' => $cheapestProducts,
            'stores' => $stores,
        ];
    }

    private function updateCartPrice(ShoppingCart $shoppingCart): void
    {
        $price = (float) StoreProduct::query()
            ->where('shopping_plan_id', $shoppingCart->shopping_plan_id)
            ->where('store_id', $shoppingCart->store_id)
            ->sum(DB::raw('price * quantity'));

        $shoppingCart->update([
            'price' => $price,
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderProductsPlanPage(Request $request, array $props = []): Response
    {
        return Inertia::render('products_plan_page', [
            'recipes' => [],
            'recipe_query' => '',
            'generated_plan' => null,
            'add_product' => null,
            ...$props,
        ]);
    }
}
