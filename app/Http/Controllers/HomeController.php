<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Banner;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', ['categories' => Category::where('is_active', true)->withCount('products')->take(6)->get(), 'featuredProducts' => Product::where('is_active', true)->where('stock', '>', 0)->latest()->take(8)->get(), 'heroBanner' => Banner::where('is_active', true)->orderBy('sort_order')->first()]);
    }
}
