<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\BoldPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceFlowTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('bold.identity_key', 'bold-test-identity');
        config()->set('bold.integrity_key', 'bold-test-integrity');
        config()->set('bold.redirection_url', 'https://example.test/checkout/resultado/{order}');

        $category = Category::create(['name' => 'Pruebas '.uniqid(), 'slug' => 'pruebas-'.uniqid(), 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Producto de prueba '.uniqid(), 'slug' => 'producto-prueba-'.uniqid(), 'description' => 'Producto para verificar el flujo de compra.', 'price' => 50000, 'stock' => 5, 'images' => [], 'is_active' => true]);
        $this->user = User::factory()->create(['phone' => '3001234567', 'shipping_address' => 'Calle de pruebas 123, Bogotá', 'email_verified_at' => now()]);
    }

    public function test_an_authenticated_customer_can_add_items_and_create_a_bold_order(): void
    {
        $this->actingAs($this->user)
            ->postJson('/cart', ['product_id' => $this->product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('items.0.quantity', 2);

        $this->actingAs($this->user)
            ->post('/checkout', ['shipping_address' => 'Calle de pruebas 123, Bogotá'])
            ->assertOk()
            ->assertSee('Pago seguro');

        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id, 'payment_status' => 'pending', 'total' => 100000]);
        $this->assertDatabaseHas('order_items', ['product_id' => $this->product->id, 'quantity' => 2, 'line_total' => 100000]);
    }

    public function test_checkout_requires_a_verified_email(): void
    {
        $this->user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->user)->get('/checkout')->assertRedirect(route('verification.notice'));
    }

    public function test_registration_creates_an_unverified_customer_and_queues_email_verification(): void
    {
        Notification::fake();
        $email = 'registro-'.uniqid().'@example.test';

        $this->post('/registro', [
            'name' => 'Nueva Cliente',
            'email' => $email,
            'phone' => '3009876543',
            'shipping_address' => 'Calle 100 # 10-20, Bogotá',
            'password' => 'Segura123!',
            'password_confirmation' => 'Segura123!',
        ])->assertRedirect(route('verification.notice'));

        $customer = User::where('email', $email)->firstOrFail();
        $this->assertNull($customer->email_verified_at);
        Notification::assertSentTo($customer, VerifyEmailNotification::class);
    }

    public function test_a_signed_bold_approval_reduces_stock_clears_purchased_cart_items_and_is_idempotent(): void
    {
        Notification::fake();
        config()->set('bold.webhook_secret', 'webhook-test-secret');
        $cart = Cart::create(['user_id' => $this->user->id, 'status' => Cart::STATUS_ACTIVE]);
        $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50000]);
        $order = Order::create(['public_id' => (string) Str::uuid(), 'user_id' => $this->user->id, 'status' => 'pending', 'payment_status' => 'pending', 'payment_provider' => 'bold', 'provider_reference' => 'ORD-TEST-'.uniqid(), 'subtotal' => 100000, 'shipping_amount' => 0, 'total' => 100000, 'shipping_address' => ['address' => 'Calle de pruebas 123']]);
        $order->items()->create(['product_id' => $this->product->id, 'product_name' => $this->product->name, 'unit_price' => 50000, 'quantity' => 2, 'line_total' => 100000]);

        $payload = json_encode(['id' => 'event-'.uniqid(), 'type' => 'SALE_APPROVED', 'subject' => 'bold-payment-123', 'data' => ['payment_id' => 'bold-payment-123', 'metadata' => ['reference' => $order->provider_reference]]], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', base64_encode($payload), 'webhook-test-secret');

        $this->call('POST', '/api/webhooks/bold', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BOLD_SIGNATURE' => $signature], $payload)->assertOk();
        $this->call('POST', '/api/webhooks/bold', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BOLD_SIGNATURE' => $signature], $payload)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'paid']);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => 3]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id, 'product_id' => $this->product->id]);
    }

    public function test_webhook_signature_verifier_rejects_tampered_bodies(): void
    {
        $service = app(BoldPaymentService::class);
        config()->set('bold.webhook_secret', 'secret');

        $this->assertTrue($service->webhookIsValid('{"ok":true}', hash_hmac('sha256', base64_encode('{"ok":true}'), 'secret')));
        $this->assertFalse($service->webhookIsValid('{"ok":false}', hash_hmac('sha256', base64_encode('{"ok":true}'), 'secret')));
    }
}
