<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function index(): View
    {
        return view('cart.index');
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->carts->activeCartFor($request->user())->load('items.product'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        return response()->json($this->carts->add($request->user(), $data['product_id'], $data['quantity']), 201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cartItem->load('cart');

        return response()->json($this->carts->updateQuantity($request->user(), $cartItem, $data['quantity']));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $cartItem->load('cart');

        return response()->json($this->carts->remove($request->user(), $cartItem));
    }

    public function syncGuest(Request $request): JsonResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'max:50'], 'items.*.product_id' => ['required', 'integer', 'exists:products,id'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        return response()->json($this->carts->mergeGuestItems($request->user(), $data['items']));
    }
}
