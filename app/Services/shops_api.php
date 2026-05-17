<?php

namespace App\Services;

use Illuminate\Support\Str;

class shops_api
{
    /**
     * @param  array<int, array{ingredient_id?: int, title?: string, quantity?: int, measurement?: string}>  $productsRequest
     * @return list<array{
     *     title: string,
     *     quantity: int,
     *     measurement: string,
     *     stores: list<array{title: string, address: string, city: string, price: float}>
     * }>
     */
    public function getProducts(array $productsRequest): array
    {
        return collect($productsRequest)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $title = (string) ($item['title'] ?? 'Product');
                $quantity = (int) ($item['quantity'] ?? 1);
                $measurement = (string) ($item['measurement'] ?? 'unit');

                $stores = $this->fakeStores();

                return [
                    'title' => $title,
                    'quantity' => $quantity,
                    'measurement' => $measurement,
                    'stores' => collect($stores)
                        ->filter(fn (array $store): bool => $this->carriesProduct($title, (string) $store['title']))
                        ->map(function (array $store) use ($title): array {
                            return [
                                ...$store,
                                'price' => $this->fakePrice($title, $store['title']),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, address: string, city: string, price: float}>
     */
    public function getShops(string $productTitle): array
    {
        $productTitle = trim($productTitle);

        if ($productTitle === '') {
            return [];
        }

        return collect($this->fakeStores())
            ->filter(fn (array $store): bool => $this->carriesProduct($productTitle, (string) $store['title']))
            ->map(fn (array $store): array => [
                ...$store,
                'price' => $this->fakePrice($productTitle, $store['title']),
            ])
            ->sortBy('price')
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, address: string, city: string}>
     */
    private function fakeStores(): array
    {
        return [
            [
                'title' => 'Maxima',
                'address' => 'Fake g. 1',
                'city' => 'Vilnius',
            ],
            [
                'title' => 'IKI',
                'address' => 'Fake g. 2',
                'city' => 'Kaunas',
            ],
            [
                'title' => 'Rimi',
                'address' => 'Fake g. 3',
                'city' => 'Klaipėda',
            ],
        ];
    }

    private function fakePrice(string $productTitle, string $storeTitle): float
    {
        // Deterministic pseudo-random price from input strings.
        $hash = crc32(Str::lower($productTitle.'|'.$storeTitle));

        // Price range ~ [0.79, 9.99]
        $cents = 79 + ($hash % (999 - 79));

        return round($cents / 100, 2);
    }

    private function carriesProduct(string $productTitle, string $storeTitle): bool
    {
        $storeTitle = trim($storeTitle);

        // Ensure at least one store always carries every product.
        if ($storeTitle === 'Maxima') {
            return true;
        }

        $len = Str::length(Str::lower(trim($productTitle)));

        return match ($storeTitle) {
            'IKI' => $len % 2 === 0,
            'Rimi' => $len % 3 === 0,
            default => false,
        };
    }
}
