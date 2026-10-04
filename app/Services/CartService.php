<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function activeCartFor(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->where('status', Cart::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            $cart ??= Cart::create(['user_id' => $user->id, 'status' => Cart::STATUS_ACTIVE]);

            return $cart;
        });
    }

    public function add(User $user, int $productId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $productId, $quantity): Cart {
            $product = Product::query()->where('is_active', true)->lockForUpdate()->findOrFail($productId);
            $cart = $this->activeCartFor($user);
            $item = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $product->id)->lockForUpdate()->first();
            $newQuantity = $quantity + ($item?->quantity ?? 0);

            $this->ensureStock($product, $newQuantity);

            if ($item) {
                $item->update(['quantity' => $newQuantity, 'unit_price' => $product->price]);
            } else {
                $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->price]);
            }

            return $this->loaded($cart);
        }, 3);
    }

    public function updateQuantity(User $user, CartItem $item, int $quantity): Cart
    {
        abort_unless($item->cart->user_id === $user->id && $item->cart->status === Cart::STATUS_ACTIVE, 404);

        return DB::transaction(function () use ($item, $quantity): Cart {
            $lockedItem = CartItem::query()->with('cart')->lockForUpdate()->findOrFail($item->id);
            $product = Product::query()->where('is_active', true)->lockForUpdate()->findOrFail($lockedItem->product_id);
            $this->ensureStock($product, $quantity);
            $lockedItem->update(['quantity' => $quantity, 'unit_price' => $product->price]);

            return $this->loaded($lockedItem->cart);
        }, 3);
    }

    /** Merge the browser's localStorage cart after a successful login or registration. */
    public function mergeGuestItems(User $user, array $items): Cart
    {
        return DB::transaction(function () use ($user, $items): Cart {
            $cart = $this->activeCartFor($user);

            foreach (collect($items)->groupBy('product_id') as $productId => $entries) {
                $requested = (int) $entries->sum('quantity');
                if ($requested < 1) {
                    continue;
                }

                $product = Product::query()->where('is_active', true)->lockForUpdate()->find($productId);
                if (! $product) {
                    continue; // Products deleted or unpublished after the guest added them are ignored.
                }

                $item = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $product->id)->lockForUpdate()->first();
                $mergedQuantity = min($product->stock, $requested + ($item?->quantity ?? 0));

                if ($mergedQuantity === 0) {
                    continue;
                }

                if ($item) {
                    $item->update(['quantity' => $mergedQuantity, 'unit_price' => $product->price]);
                } else {
                    $cart->items()->create(['product_id' => $product->id, 'quantity' => $mergedQuantity, 'unit_price' => $product->price]);
                }
            }

            return $this->loaded($cart);
        }, 3);
    }

    public function remove(User $user, CartItem $item): Cart
    {
        abort_unless($item->cart->user_id === $user->id && $item->cart->status === Cart::STATUS_ACTIVE, 404);
        $cart = $item->cart;
        $item->delete();

        return $this->loaded($cart);
    }

    private function ensureStock(Product $product, int $quantity): void
    {
        if ($quantity > $product->stock) {
            throw ValidationException::withMessages(['quantity' => "Solo hay {$product->stock} unidades disponibles de {$product->name}."]);
        }
    }

    private function loaded(Cart $cart): Cart
    {
        return $cart->fresh(['items.product']) ?? $cart;
    }
}
