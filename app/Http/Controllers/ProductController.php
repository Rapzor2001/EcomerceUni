<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'exists:categories,slug'], 'min_price' => ['nullable', 'numeric', 'min:0'], 'max_price' => ['nullable', 'numeric', 'gte:min_price']]);
        $products = Product::query()->with('category')->where('is_active', true)->when($data['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search->where('name', 'ilike', "%{$term}%")->orWhere('description', 'ilike', "%{$term}%")))->when($data['category'] ?? null, fn ($query, $slug) => $query->whereHas('category', fn ($category) => $category->where('slug', $slug)))->when($data['min_price'] ?? null, fn ($query, $price) => $query->where('price', '>=', $price))->when($data['max_price'] ?? null, fn ($query, $price) => $query->where('price', '<=', $price))->latest()->paginate(12)->withQueryString();

        return view('products.index', ['products' => $products, 'categories' => Category::where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        return view('products.show', compact('product'));
    }
}
