<?php

namespace App\Services;

class products_service
{
    public function __construct(private readonly shops_api $shopsApi) {}

    /**
     * @param  list<array{title: string, quantity: int, measurement: string}>  $productsRequest
     * @return list<array{
     *     title: string,
     *     quantity: int,
     *     measurement: string,
     *     stores: list<array{title: string, address: string, city: string, price: float}>
     * }>
     */
    public function getProducts(array $productsRequest): array
    {
        return $this->shopsApi->getProducts($productsRequest);
    }

    /**
     * @return list<array{title: string, address: string, city: string, price: float}>
     */
    public function getShops(string $productTitle): array
    {
        return $this->shopsApi->getShops($productTitle);
    }
}
