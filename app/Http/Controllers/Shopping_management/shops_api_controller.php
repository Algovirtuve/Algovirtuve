<?php

namespace App\Http\Controllers\Shopping_management;

use App\Http\Controllers\Controller;
use App\Services\shops_api;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class shops_api_controller extends Controller
{
    public function __construct(private readonly shops_api $shopsApi) {}

    public function getProducts(Request $request): JsonResponse
    {
        $products = $request->input('products');

        if (! is_array($products)) {
            $products = [];
        }

        return response()->json([
            'products' => $this->shopsApi->getProducts($products),
        ]);
    }

    public function getShops(Request $request): JsonResponse
    {
        return response()->json([
            'shops' => $this->shopsApi->getShops((string) $request->string('query')),
        ]);
    }
}
