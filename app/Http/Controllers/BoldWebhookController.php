<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderConfirmedNotification;
use App\Services\BoldPaymentService;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BoldWebhookController extends Controller
{
    public function __construct(private readonly BoldPaymentService $bold, private readonly WhatsAppCloudService $whatsapp) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        if (! $this->bold->webhookIsValid($rawPayload, $request->header('X-Bold-Signature'))) {
            Log::warning('Rejected Bold webhook due to invalid signature.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON'], 422);
        }

        $reference = data_get($payload, 'data.metadata.reference') ?? data_get($payload, 'data.reference');
        if (! is_string($reference) || $reference === '') {
            Log::warning('Ignored Bold webhook without an external order reference.', ['event_id' => $payload['id'] ?? null]);

            return response()->json(['received' => true], 202);
        }

        $type = strtoupper((string) ($payload['type'] ?? ''));
        $paidOrder = null;

        DB::transaction(function () use ($reference, $type, $payload, &$paidOrder): void {
            $order = Order::query()->where('provider_reference', $reference)->lockForUpdate()->first();
            if (! $order) {
                Log::warning('Ignored Bold webhook for unknown order.', ['reference' => $reference]);

                return;
            }

            $transactionId = data_get($payload, 'data.payment_id') ?? data_get($payload, 'subject');
            $order->forceFill(['provider_transaction_id' => $transactionId, 'provider_payload' => $payload])->save();

            if ($type === 'SALE_APPROVED') {
                if ($order->payment_status === 'paid') {
                    return;
                }

                $orderItems = $order->items()->lockForUpdate()->get();
                $products = [];
                foreach ($orderItems as $orderItem) {
                    $product = Product::query()->lockForUpdate()->find($orderItem->product_id);
                    if (! $product || $product->stock < $orderItem->quantity) {
                        $order->update(['status' => 'paid_review', 'payment_status' => 'paid', 'paid_at' => now()]);
                        Log::critical('Paid Bold order requires inventory review.', ['order' => $order->provider_reference]);

                        return;
                    }
                    $products[$orderItem->product_id] = $product;
                }

                foreach ($orderItems as $orderItem) {
                    $product = $products[$orderItem->product_id];
                    $product->decrement('stock', $orderItem->quantity);
                }

                $order->update(['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()]);
                $cart = Cart::query()->where('user_id', $order->user_id)->where('status', Cart::STATUS_ACTIVE)->lockForUpdate()->first();
                if ($cart) {
                    foreach ($orderItems as $orderItem) {
                        $cartItem = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $orderItem->product_id)->lockForUpdate()->first();
                        if ($cartItem) {
                            $cartItem->quantity <= $orderItem->quantity ? $cartItem->delete() : $cartItem->decrement('quantity', $orderItem->quantity);
                        }
                    }
                }
                $paidOrder = $order->fresh(['items', 'user']);
            }

            if (in_array($type, ['SALE_REJECTED', 'VOID_APPROVED'], true) && $order->payment_status !== 'paid') {
                $order->update(['status' => 'failed', 'payment_status' => 'failed']);
            }
        }, 3);

        if ($paidOrder?->user) {
            $paidOrder->user->notify(new OrderConfirmedNotification($paidOrder));
            $this->whatsapp->purchaseConfirmed($paidOrder);
        }

        return response()->json(['received' => true]);
    }
}
