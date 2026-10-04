<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\BoldPaymentService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $carts, private readonly BoldPaymentService $bold) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->activeCartFor($request->user())->load('items.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Agrega al menos un producto antes de continuar.');
        }

        return view('checkout.index', compact('cart'));
    }

    public function store(Request $request): View|RedirectResponse
    {
        $data = $request->validate(['shipping_address' => ['required', 'string', 'min:10', 'max:500']]);
        $redirectionUrl = $this->bold->redirectionUrl('pending');

        if ($error = $this->bold->configurationError($redirectionUrl)) {
            return redirect()->route('checkout.index')->with('error', $error);
        }

        $user = $request->user();
        $user->update(['shipping_address' => $data['shipping_address']]);

        $order = DB::transaction(function () use ($user, $data): Order {
            $cart = Cart::query()->where('user_id', $user->id)->where('status', Cart::STATUS_ACTIVE)->lockForUpdate()->firstOrFail();
            $items = CartItem::query()->where('cart_id', $cart->id)->lockForUpdate()->get();
            abort_if($items->isEmpty(), 422, 'El carrito está vacío.');
            $subtotal = 0.0;

            foreach ($items as $item) {
                $product = Product::query()->where('is_active', true)->lockForUpdate()->findOrFail($item->product_id);
                abort_if($item->quantity > $product->stock, 422, "No hay suficiente inventario para {$product->name}.");
                $item->forceFill(['unit_price' => $product->price])->save();
                $subtotal += (float) $product->price * $item->quantity;
            }

            $settings = SystemSetting::query()->whereIn('key', ['shipping_flat_rate', 'free_shipping_threshold'])->pluck('value', 'key');
            $flatRate = (float) data_get($settings, 'shipping_flat_rate.value', 0);
            $freeThreshold = (float) data_get($settings, 'free_shipping_threshold.value', 0);
            $shipping = $freeThreshold > 0 && $subtotal >= $freeThreshold ? 0.0 : $flatRate;
            $reference = 'ORD-'.Str::upper(Str::random(24));
            $order = Order::create(['public_id' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'pending', 'payment_provider' => 'bold', 'provider_reference' => $reference, 'subtotal' => $subtotal, 'shipping_amount' => $shipping, 'total' => $subtotal + $shipping, 'shipping_address' => ['address' => $data['shipping_address'], 'recipient' => $user->name, 'phone' => $user->phone]]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item->product_id);
                $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => $product->price, 'quantity' => $item->quantity, 'line_total' => (float) $product->price * $item->quantity]);
            }

            return $order->load('items');
        }, 3);

        return view('checkout.index', ['order' => $order, 'boldButton' => $this->bold->buttonData($order)]);
    }

    public function success(Request $request, string $order): View
    {
        $purchase = Order::query()->where('public_id', $order)->where('user_id', $request->user()->id)->with('items')->firstOrFail();

        return view('checkout.success', ['order' => $purchase]);
    }
}
