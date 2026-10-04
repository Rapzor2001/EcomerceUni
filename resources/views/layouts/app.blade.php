<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'NOIR DISTRICT') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}"><link rel="stylesheet" href="{{ asset('css/fashion.css') }}"><script defer src="{{ asset('js/store.js') }}"></script>
</head>
<body data-authenticated="{{ auth()->check() ? 'true' : 'false' }}" data-sync-guest="{{ session('sync_guest_cart') ? 'true' : 'false' }}">
<nav class="navbar"><div class="container nav-inner"><a class="brand" href="{{ route('home') }}">NOIR DISTRICT</a><div class="nav-links"><a href="{{ route('home') }}">Home</a><a href="{{ route('products.index') }}">Shop</a><a class="cart-link" href="{{ route('cart.index') }}">Bag <span class="badge" data-cart-badge>0</span></a>@auth @if(auth()->user()->role === 'admin')<a href="{{ route('admin.dashboard') }}">Admin</a>@endif @if(!auth()->user()->hasVerifiedEmail())<a href="{{ route('verification.notice') }}">Verificar</a>@endif<form action="{{ route('logout') }}" method="POST">@csrf<button class="secondary">Salir</button></form>@else<a href="{{ route('login') }}">Ingresar</a><a href="{{ route('register') }}">Cuenta</a>@endauth</div></div></nav>
<main class="container">@if(session('status'))<p class="alert">{{ session('status') }}</p>@endif @if(session('error'))<p class="error">{{ session('error') }}</p>@endif @yield('content')</main>
<footer class="footer"><div class="container footer-grid"><div><strong class="footer-mark">NOIR<br>DISTRICT</strong><p class="footer-copy">Una mirada propia para una ciudad que nunca baja el ritmo.</p></div><div><span class="footer-label">Explorar</span><a href="{{ route('products.index') }}">Nuevos drops</a><a href="{{ route('products.index', ['category' => 'hoodies']) }}">Hoodies</a><a href="{{ route('products.index', ['category' => 'chaquetas']) }}">Outerwear</a></div><div><span class="footer-label">Conecta</span><a href="mailto:noirdistrictcol@gmail.com">noirdistrictcol@gmail.com</a><a href="https://instagram.com" target="_blank" rel="noopener noreferrer">Instagram</a><span>Bogotá · Colombia</span></div><div><span class="footer-label">Distrito 01</span><p class="footer-copy">Ediciones cortas.<br>Piezas para repetir.</p><span class="footer-mono">© {{ now()->year }}</span></div></div></footer>
</body>
</html>
