<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OrderShippedNotification;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $today = now()->startOfDay(); $month = now()->startOfMonth();
        $paid = Order::query()->where('payment_status', 'paid');
        $weekly = $paid->clone()->where('paid_at', '>=', now()->subDays(6)->startOfDay())->selectRaw('DATE(paid_at) AS report_day, SUM(total) AS total')->groupByRaw('DATE(paid_at)')->orderByRaw('DATE(paid_at)')->get();
        $topDrops = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.payment_status', 'paid')->selectRaw('product_name, SUM(quantity) units, SUM(line_total) revenue')->groupBy('product_name')->orderByDesc('units')->limit(5)->get();

        return view('admin.dashboard', [
            'kpis' => ['today' => $paid->clone()->where('paid_at', '>=', $today)->sum('total'), 'month' => $paid->clone()->where('paid_at', '>=', $month)->sum('total'), 'toShip' => Order::where('payment_status', 'paid')->whereIn('fulfillment_status', ['pending', 'preparing'])->count(), 'lowStock' => Product::whereColumn('stock', '<=', 'low_stock_threshold')->where('is_active', true)->count(), 'customers' => User::where('role', 'customer')->count()],
            'weekly' => $weekly, 'topDrops' => $topDrops,
            'alerts' => ['payments' => Order::where('payment_status', 'paid')->where('paid_at', '>=', now()->subDay())->latest('paid_at')->take(5)->get(), 'lowStock' => Product::whereColumn('stock', '<=', 'low_stock_threshold')->where('is_active', true)->orderBy('stock')->take(8)->get()],
        ]);
    }

    public function products(Request $request): View { return view('admin.products.index', ['products' => Product::with('category')->latest()->paginate(15), 'categories' => Category::orderBy('name')->get()]); }
    public function productForm(?Product $product = null): View { return view('admin.products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]); }
    public function saveProduct(Request $request, ?Product $product = null): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', 'exists:categories,id'], 'name' => ['required', 'string', 'max:180'], 'sku' => ['nullable', 'string', 'max:80', 'unique:products,sku'.($product ? ','.$product->id : '')], 'description' => ['required', 'string', 'max:5000'], 'price' => ['required', 'numeric', 'min:1000'], 'stock' => ['required', 'integer', 'min:0'], 'low_stock_threshold' => ['required', 'integer', 'min:0'], 'tags' => ['nullable', 'string', 'max:500'], 'variants' => ['nullable', 'string', 'max:5000'], 'images.*' => ['image', 'max:4096'], 'is_active' => ['nullable', 'boolean']]);
        $variants = json_decode($data['variants'] ?: '[]', true); if (! is_array($variants)) return back()->withInput()->withErrors(['variants' => 'Las variantes deben estar en JSON válido.']);
        $images = $product?->images ?? []; foreach ($request->file('images', []) as $image) $images[] = Storage::disk('public')->url($image->store('products', 'public'));
        $payload = [...$data, 'slug' => $product?->slug ?? Str::slug($data['name']).'-'.Str::lower(Str::random(5)), 'variants' => $variants, 'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->values()->all(), 'images' => $images, 'is_active' => $request->boolean('is_active')];
        DB::transaction(fn () => $product ? $product->update($payload) : Product::create($payload));
        return redirect()->route('admin.products')->with('status', 'Producto guardado correctamente.');
    }
    public function categories(): View { return view('admin.categories', ['categories' => Category::withCount('products')->orderBy('name')->get()]); }
    public function saveCategory(Request $request): RedirectResponse { $data = $request->validate(['name' => ['required','string','max:100','unique:categories,name'], 'description' => ['nullable','string','max:1000']]); Category::create([...$data, 'slug' => Str::slug($data['name']), 'is_active' => true]); return back()->with('status', 'Colección creada.'); }
    public function orders(Request $request): View { $orders = Order::with('user')->when($request->status, fn ($q, $status) => $q->where('payment_status', $status))->when($request->fulfillment, fn ($q, $status) => $q->where('fulfillment_status', $status))->latest()->paginate(20)->withQueryString(); return view('admin.orders.index', compact('orders')); }
    public function order(Order $order, WhatsAppCloudService $whatsapp): View { return view('admin.orders.show', ['order' => $order->load('user', 'items.product'), 'whatsappUrl' => $whatsapp->chatUrl($order->loadMissing('user'))]); }
    public function updateOrder(Request $request, Order $order, WhatsAppCloudService $whatsapp): RedirectResponse
    {
        $data = $request->validate(['fulfillment_status' => ['required', 'in:pending,preparing,shipped,delivered,cancelled'], 'shipping_carrier' => ['nullable', 'string', 'max:100'], 'tracking_number' => ['nullable', 'string', 'max:120']]);
        $shippedOrder = DB::transaction(function () use ($order, $data) {
            $order = Order::query()->with('user')->lockForUpdate()->findOrFail($order->id);
            $becameShipped = $data['fulfillment_status'] === 'shipped' && $order->fulfillment_status !== 'shipped';
            $order->update([...$data, 'shipped_at' => $becameShipped ? now() : $order->shipped_at, 'delivered_at' => $data['fulfillment_status'] === 'delivered' ? now() : $order->delivered_at]);

            return $becameShipped && $order->payment_status === 'paid' ? $order->fresh('user') : null;
        }, 3);

        if ($shippedOrder?->user) {
            $shippedOrder->user->notify(new OrderShippedNotification($shippedOrder));
            $whatsapp->shipmentCreated($shippedOrder);
        }
        return back()->with('status', 'Despacho actualizado.');
    }
    public function customers(): View { return view('admin.customers', ['customers' => User::where('role', 'customer')->withCount(['orders as paid_orders_count' => fn ($q) => $q->where('payment_status','paid')])->latest()->paginate(20)]); }
    public function settings(): View { return view('admin.settings', ['settings' => SystemSetting::pluck('value', 'key'), 'banners' => Banner::orderBy('sort_order')->get()]); }
    public function saveSettings(Request $request): RedirectResponse { $data = $request->validate(['shipping_flat_rate' => ['required','numeric','min:0'], 'free_shipping_threshold' => ['required','numeric','min:0'], 'bold_mode' => ['required','in:sandbox,production']]); foreach ($data as $key => $value) SystemSetting::updateOrCreate(['key' => $key], ['value' => ['value' => $value]]); return back()->with('status', 'Ajustes guardados. Las llaves secretas se gestionan exclusivamente mediante .env.'); }
    public function saveBanner(Request $request): RedirectResponse { $data = $request->validate(['title' => ['required','string','max:150'], 'subtitle' => ['nullable','string','max:255'], 'link_url' => ['nullable','url','max:255'], 'image' => ['required','image','max:4096'], 'sort_order' => ['nullable','integer','min:0']]); $data['image_path'] = Storage::disk('public')->url($request->file('image')->store('banners','public')); unset($data['image']); Banner::create([...$data,'is_active' => true]); return back()->with('status','Banner publicado.'); }
}
