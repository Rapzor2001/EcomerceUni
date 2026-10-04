<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OrderShippedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBackofficeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_cannot_access_the_backoffice(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_dashboard_and_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Dashboard operativo');
        $this->actingAs($admin)->get('/admin/ajustes')->assertOk()->assertSee('Configuración global');
    }

    public function test_admin_can_create_catalog_settings_and_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Drops '.uniqid(), 'slug' => 'drops-'.uniqid(), 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/productos', [
            'category_id' => $category->id, 'name' => 'Hoodie Test '.uniqid(), 'sku' => 'HD-'.Str::upper(Str::random(6)),
            'description' => 'Hoodie de prueba para validar el flujo administrativo.', 'price' => 190000, 'stock' => 12, 'low_stock_threshold' => 3,
            'tags' => 'Limited Drop, Thrifted Culture', 'variants' => '[{"size":"M","color":"Black","stock":6}]', 'is_active' => 1,
            'images' => [UploadedFile::fake()->create('hoodie.jpg', 10, 'image/jpeg')],
        ])->assertRedirect(route('admin.products'));

        $product = Product::where('name', 'like', 'Hoodie Test%')->latest()->firstOrFail();
        $this->assertSame(['Limited Drop', 'Thrifted Culture'], $product->tags);
        $this->assertSame('M', $product->variants[0]['size']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $product->images[0]));

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id, 'name' => 'Hoodie actualizado', 'sku' => $product->sku,
            'description' => 'Producto actualizado desde el panel.', 'price' => 210000, 'stock' => 9, 'low_stock_threshold' => 2,
            'tags' => 'Updated Drop', 'variants' => '[{"size":"XL","color":"Grey","stock":9}]', 'is_active' => 1,
        ])->assertRedirect(route('admin.products'));
        $product->refresh();
        $this->assertSame('Hoodie actualizado', $product->name);
        $this->assertSame(9, $product->stock);
        $this->assertSame(['Updated Drop'], $product->tags);
        $this->assertSame('XL', $product->variants[0]['size']);

        $this->actingAs($admin)->put('/admin/ajustes', ['shipping_flat_rate' => 15000, 'free_shipping_threshold' => 250000, 'bold_mode' => 'sandbox'])->assertRedirect();
        $this->assertSame(15000, data_get(SystemSetting::where('key', 'shipping_flat_rate')->firstOrFail()->value, 'value'));

        $this->actingAs($admin)->post('/admin/banners', ['title' => 'Drop de prueba', 'subtitle' => 'Edición limitada', 'sort_order' => 1, 'image' => UploadedFile::fake()->create('banner.jpg', 10, 'image/jpeg')])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Drop de prueba');
    }

    public function test_admin_can_dispatch_paid_order_and_customer_is_notified(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create(['public_id' => (string) Str::uuid(), 'user_id' => $customer->id, 'status' => 'paid', 'payment_status' => 'paid', 'fulfillment_status' => 'preparing', 'payment_provider' => 'bold', 'provider_reference' => 'ORD-ADMIN-'.Str::upper(Str::random(8)), 'subtotal' => 100000, 'shipping_amount' => 0, 'total' => 100000, 'shipping_address' => ['address' => 'Calle 1 # 2-3']]);

        $this->actingAs($admin)->put(route('admin.orders.update', $order), ['fulfillment_status' => 'shipped', 'shipping_carrier' => 'Coordinadora', 'tracking_number' => 'TRK123456'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'fulfillment_status' => 'shipped', 'shipping_carrier' => 'Coordinadora', 'tracking_number' => 'TRK123456']);
        Notification::assertSentTo($customer, OrderShippedNotification::class);
    }
}
