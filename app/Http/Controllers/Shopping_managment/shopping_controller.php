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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class shopping_controller extends Controller
{
    public function showProductsPlanPage(): Response
    {
        return Inertia::render('products_plan_page', [
            'recipes' => [],
            'recipe_query' => '',
            'generated_plan' => null,
            'add_product' => null,
        ]);
    }
  
    public function getRecipes(GetRecipesRequest $request): Response
    {
        $query = trim((string) $request->input('query', ''));

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

        return Inertia::render('products_plan_page', [
            'generated_plan' => null,
            'add_product' => null,
            'recipes' => $recipes,
            'recipe_query' => $query,
        ]);
    }

    public function generateProductsPlan(GenerateProductsPlanRequest $request, products_service $productsService): Response
    {
        $userIngredients = $request->user()->ingredients()->with('product')->get();

        $recipesWithIngredients = Recipe::query()
            ->whereKey($request->recipeIds())
            ->with(['ingredients.product'])
            ->get();

        $missingIngredients = $this->checkMissingIngredients($userIngredients, $recipesWithIngredients);

        if ($missingIngredients === []) {
            return Inertia::render('products_plan_page', [
                'recipes' => [],
                'recipe_query' => '',
                'generated_plan' => null,
                'add_product' => null,
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

        $storesFromApi = collect($productsFromApi)
            ->flatMap(static fn (array $product): array => is_array($product['stores'] ?? null) ? $product['stores'] : [])
            ->values()
            ->all();

        $storesFromDb = Store::query()
            ->whereIn('title', collect($storesFromApi)->pluck('title')->all())
            ->get();

        $storeByTitle = $this->checkShops($storesFromApi, $storesFromDb);

        $shoppingPlan = insert(ShoppingPlan::class, [
            'user_id' => $request->user()->id,
            'generation_date' => now()->toDateString(),
        ]);

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

            $ingredientId = (int) $missing['ingredient_id'];

            insert(StoreProduct::class, [
                'store_id' => $store->id,
                'shopping_plan_id' => $shoppingPlan->id,
                'product_id' => Product::query()->where('ingredient_id', $ingredientId)->first()->id,
                'price' => (float) ($selectedStore['price'] ?? 0),
                'quantity' => (int) ($missing['quantity'] ?? 1),
            ]);
        }

        $storeProducts = $shoppingPlan->storeProducts()->get();

        // if ($storeProducts->isEmpty()) {
        //     return Inertia::render('products_plan_page', [
        //         'recipes' => [],
        //         'recipe_query' => '',
        //         'generated_plan' => [
        //             'id' => (int) $shoppingPlan->id,
        //             'generation_date' => (string) $shoppingPlan->generation_date->toDateString(),
        //             'cheapest_products' => [],
        //             'stores' => [],
        //         ],
        //         'add_product' => null,
        //     ]);
        // }

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

        $fullBasketStoreTitles = array_values($fullBasketStoreTitles ?? []);
        $desiredStoreIds = collect($fullBasketStoreTitles)
            ->map(function (string $storeTitle) use ($storeByTitle): ?int {
                $store = $storeByTitle[(string) $storeTitle] ?? null;

                return $store instanceof Store ? (int) $store->id : null;
            })
            ->filter(static fn (?int $id): bool => $id !== null)
            ->values()
            ->all();

        if ($desiredStoreIds === []) {
            ShoppingCart::query()->where('shopping_plan_id', $shoppingPlan->id)->delete();
        } else {
            ShoppingCart::query()
                ->where('shopping_plan_id', $shoppingPlan->id)
                ->delete();

            foreach ($fullBasketStoreTitles as $storeTitle) {
                $storeTitle = (string) $storeTitle;
                $store = $storeByTitle[$storeTitle] ?? null;

                if (! $store instanceof Store) {
                    continue;
                }

                $total = (float) $storeProducts->sum(function (StoreProduct $storeProduct) use ($pricesByProductId, $storeTitle): float {
                    $productId = (int) $storeProduct->product_id;
                    $unitPrice = (float) ($pricesByProductId[$productId][$storeTitle] ?? 0);

                    return $unitPrice * (int) $storeProduct->quantity;
                });

                insert(ShoppingCart::class, [
                    'shopping_plan_id' => $shoppingPlan->id,
                    'store_id' => $store->id,
                    'price' => $total,
                ]);
            }
        }

        $cheapestProducts = $storeProducts
            ->map(function (StoreProduct $storeProduct) use ($cheapestByProductId, $storeByTitle): array {
                $productId = (int) $storeProduct->product_id;
                $cheapest = $cheapestByProductId[$productId] ?? ['store_title' => '', 'price' => 0];

                $storeTitle = (string) ($cheapest['store_title'] ?? '');
                $store = $storeByTitle[$storeTitle] ?? null;

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
            ->map(function (ShoppingCart $cart) use ($storeProducts, $pricesByProductId): array {
                /** @var Store $store */
                $store = $cart->store;
                $storeTitle = (string) $store->title;

                $products = $storeProducts
                    ->map(function (StoreProduct $storeProduct) use ($pricesByProductId, $storeTitle): array {
                        $productId = (int) $storeProduct->product_id;
                        $unitPrice = (float) ($pricesByProductId[$productId][$storeTitle] ?? 0);

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

        return Inertia::render('products_plan_page', [
            'recipes' => [],
            'recipe_query' => '',
            'generated_plan' => [
                'id' => (int) $shoppingPlan->id,
                'generation_date' => (string) $shoppingPlan->generation_date->toDateString(),
                'cheapest_products' => $cheapestProducts,
                'stores' => $stores,
            ],
            'add_product' => null,
        ]);
    }

    public function searchForProducts(InsertNewProductRequest $request, products_service $productsService): Response
    {
        $shops = $productsService->getShops($request->productTitle());
 
        if ($this->validate($shops)) {
            return Inertia::render('products_plan_page', [
                'recipes' => [],
                'recipe_query' => '',
                'generated_plan' => $request['generated_plan'] ?? null,
                'add_product' => [
                    'product_title' => $request->productTitle(),
                    'shops' => $shops,
                ],
            ]);
        }

        return Inertia::render('products_plan_page', [
            'recipes' => [],
            'recipe_query' => '',
            'generated_plan' => $request['generated_plan'] ?? null,
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

    public function insertNewProduct(InsertNewProductRequest $request, ShoppingPlan $shoppingPlan): Response {
        $user = $request->user();

        if ((int) $shoppingPlan->user_id !== (int) $user->id) {
            abort(403);
        }

        $store = $this->checkShop($request->selectedShop()['title']);

        $title = $request->productTitle();
        $selectedProduct = $request->selectedProduct();

        $product = insert(Product::class, [
            'title' => $title,
            'measurement' => $selectedProduct['measurement'],
            'quantity' => (int) $selectedProduct['quantity'],
        ]);

        $storeProduct = insert(StoreProduct::class, [
            'store_id' => $store->id,
            'shopping_plan_id' => $shoppingPlan->id,
            'product_id' => $product->id,
            'price' => (float) $selectedProduct['price'],
            'quantity' => (int) $selectedProduct['quantity'],
        ]);

        $shoppingPlan->update([
            'generation_date' => now()->toDateString(),
        ]);

        ShoppingCart::query()
            ->where('shopping_plan_id', $shoppingPlan->id)
            ->where('store_id', $store->id)
            ->update([
                'price' => DB::raw('price + '.((float) $selectedProduct['price'] * (int) $selectedProduct['quantity'])),
            ]);

        $generatedPlan = $request->input('generated_plan');

        $generatedPlan['cheapest_products'][] = [
            'store_product_id' => (int) $storeProduct->id,
            'product_id' => (int) $product->id,
            'title' => (string) $product->title,
            'quantity' => (int) $storeProduct->quantity,
            'measurement' => $product->measurement->value,
            'price' => (float) $selectedProduct['price'],
            'store_title' => (string) $store->title,
            'store_city' => (string) $store->city,
        ];

        $generatedPlan['stores'] = collect($generatedPlan['stores'] ?? [])
            ->map(function (array $shop) use ($storeProduct, $product, $selectedProduct): array {
                if ($shop['id'] !== (int) $storeProduct->store_id) {
                    return $shop;
                }

                $shop['cart_price'] = (float) $shop['cart_price'] +
                    ((float) $selectedProduct['price'] * (int) $selectedProduct['quantity']);

                $shop['products'][] = [
                    'store_product_id' => (int) $storeProduct->id,
                    'product_id' => (int) $product->id,
                    'title' => (string) $product->title,
                    'quantity' => (int) $storeProduct->quantity,
                    'measurement' => $product->measurement->value,
                    'price' => (float) $selectedProduct['price'],
                ];

                return $shop;
            })
            ->values()
            ->all();

        $products = collect($generatedPlan['cheapest_products'])
            ->pluck('title')
            ->unique()
            ->values()
            ->all();

        $generatedPlan['stores'] = collect($generatedPlan['stores'])
            ->filter(function (array $store) use ($products): bool {
                return collect($store['products'] ?? [])->pluck('title')->intersect($products)->count() === count($products);
            })
            ->values()
            ->all();

        if (empty($generatedPlan['stores']) && count($generatedPlan['cheapest_products']) === 1) {
            $generatedPlan['stores'][] = [
                'id' => (int) $store->id,
                'title' => (string) $store->title,
                'address' => (string) $store->address,
                'city' => (string) $store->city,
                'cart_price' => (float) $selectedProduct['price'] * (int) $selectedProduct['quantity'],
                'products' => [[
                    'store_product_id' => (int) $storeProduct->id,
                    'product_id' => (int) $product->id,
                    'title' => (string) $product->title,
                    'quantity' => (int) $storeProduct->quantity,
                    'measurement' => $product->measurement->value,
                    'price' => (float) $selectedProduct['price'],
                ]],
            ];
        }

        return Inertia::render('products_plan_page', [
            'recipes' => [],
            'recipe_query' => '',
            'generated_plan' => $generatedPlan,
            'add_product' => null,
            'flash' => [
                'toast' => [
                    'type' => 'success',
                    'message' => 'Product added successfully.',
                ],
            ],
        ]);
    }

public function removeProduct(RemoveProductRequest $request, ShoppingPlan $shoppingPlan, int $productId): Response
    {
        $user = $request->user();
        if ((int) $shoppingPlan->user_id !== (int) $user->id) {
            abort(403);
        }

        $storeProducts = $shoppingPlan->storeProducts()->where('product_id', $productId)->get();

        foreach ($storeProducts as $storeProduct) {
            $delta = $storeProduct->price * $storeProduct->quantity;
            
            $storeProduct->delete();

            ShoppingCart::query()
                ->where('shopping_plan_id', $shoppingPlan->id)
                ->where('store_id', $storeProduct->store_id)
                ->update(['price' => DB::raw('price - ' . $delta)]);
        }

        if ($shoppingPlan->storeProducts()->count() === 0) {
            ShoppingCart::query()
                ->where('shopping_plan_id', $shoppingPlan->id)
                ->delete();
        } else {
            $activeStoreIds = $shoppingPlan->storeProducts()->pluck('store_id')->unique();
            ShoppingCart::query()
                ->where('shopping_plan_id', $shoppingPlan->id)
                ->whereNotIn('store_id', $activeStoreIds)
                ->delete();
        }

        $generatedPlan = $request->input('generated_plan');

        if (is_array($generatedPlan)) {
            $generatedPlan['cheapest_products'] = collect($generatedPlan['cheapest_products'] ?? [])
                ->reject(fn ($item) => (int) ($item['product_id'] ?? 0) === $productId)
                ->values()
                ->all();

            $generatedPlan['stores'] = collect($generatedPlan['stores'] ?? [])
                ->map(function (array $store) use ($productId) {
                    
                    $productsToRemove = collect($store['products'] ?? [])
                        ->filter(fn ($p) => (int) ($p['product_id'] ?? 0) === $productId);

                    $delta = $productsToRemove->sum(fn ($p) => $p['price'] * $p['quantity']);

                    $store['products'] = collect($store['products'] ?? [])
                        ->reject(fn ($p) => (int) ($p['product_id'] ?? 0) === $productId)
                        ->values()
                        ->all();

                    $store['cart_price'] = max(0, (float) ($store['cart_price'] ?? 0) - $delta);

                    return $store;
                })
                ->reject(fn (array $store) => empty($store['products']))
                ->values()
                ->all();
        }

        return Inertia::render('products_plan_page', [
            'recipes'        => [],
            'recipe_query'   => '',
            'generated_plan' => $generatedPlan,
            'add_product'    => null,
            'flash'          => [
                'toast' => [
                    'type'    => 'success',
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
    private function checkMissingIngredients(Collection $userIngredients, Collection $recipes): array
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
            ->map(function (Collection $rows): array {
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
    private function checkShops(array $shops, Collection $existingStores): array
    {
        $storeByTitle = [];

        foreach ($shops as $shop) {
            $title = (string) ($shop['title'] ?? '');

            if ($title === '' || isset($storeByTitle[$title])) {
                continue;
            }

            if (!$existingStores->contains('title', $title)) {
                $storeByTitle[$title] = insert(Store::class, [
                    'title' => $title,
                    'address' => trim((string) $shop['address']),
                    'city' => trim((string) $shop['city']),
                ]);
            }
            else {
                $storeByTitle[$title] = $existingStores->where('title', $title)->first();
            }
        }

        return $storeByTitle;
    }

    private function checkShop(?string $shopTitle): ?Store
    {
        if ($shopTitle === null) {
            return null;
        }

        return Store::query()->where('title', $shopTitle)->first();
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
}
