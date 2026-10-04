<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('guest')->group(function () {
    Route::get('/registro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/ingresar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/recuperar-clave', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/recuperar-clave', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/restablecer-clave/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/restablecer-clave', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/salir', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/verificar-correo', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verificar-correo/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/verificar-correo/reenvio', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::get('/checkout', [CheckoutController::class, 'index'])->middleware(['auth', 'verified'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware(['auth', 'verified', 'throttle:10,1'])->name('checkout.store');
Route::get('/checkout/resultado/{order}', [CheckoutController::class, 'success'])->middleware(['auth', 'verified'])->name('checkout.success');

Route::middleware('auth')->prefix('cart')->name('cart.')->group(function () {
    Route::get('/data', [CartController::class, 'show'])->name('show');
    Route::post('/', [CartController::class, 'store'])->name('store');
    Route::patch('/items/{cartItem}', [CartController::class, 'update'])->name('items.update');
    Route::delete('/items/{cartItem}', [CartController::class, 'destroy'])->name('items.destroy');
    Route::post('/sync-guest', [CartController::class, 'syncGuest'])->name('sync-guest');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/productos', [AdminController::class, 'products'])->name('products');
    Route::get('/productos/crear', [AdminController::class, 'productForm'])->name('products.create');
    Route::post('/productos', [AdminController::class, 'saveProduct'])->name('products.store');
    Route::get('/productos/{product}/editar', [AdminController::class, 'productForm'])->name('products.edit');
    Route::put('/productos/{product}', [AdminController::class, 'saveProduct'])->name('products.update');
    Route::get('/colecciones', [AdminController::class, 'categories'])->name('categories');
    Route::post('/colecciones', [AdminController::class, 'saveCategory'])->name('categories.store');
    Route::get('/pedidos', [AdminController::class, 'orders'])->name('orders');
    Route::get('/pedidos/{order}', [AdminController::class, 'order'])->name('orders.show');
    Route::put('/pedidos/{order}', [AdminController::class, 'updateOrder'])->name('orders.update');
    Route::get('/clientes', [AdminController::class, 'customers'])->name('customers');
    Route::get('/ajustes', [AdminController::class, 'settings'])->name('settings');
    Route::put('/ajustes', [AdminController::class, 'saveSettings'])->name('settings.update');
    Route::post('/banners', [AdminController::class, 'saveBanner'])->name('banners.store');
});
