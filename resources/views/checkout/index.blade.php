@extends('layouts.app')
@section('content')
@if(isset($order, $boldButton))
@php
    $customerData = json_encode([
        'email' => auth()->user()->email,
        'fullName' => auth()->user()->name,
        'phone' => auth()->user()->phone,
        'dialCode' => '+57',
    ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp
<section class="auth-card"><p class="muted">Pedido creado</p><h1>Pago seguro</h1><p>Pedido <strong>{{ $order->provider_reference }}</strong> · Total <strong>${{ number_format($order->total, 0, ',', '.') }} COP</strong></p><p class="muted">Completa el pago en la pasarela segura de Bold. La factura se confirmará cuando recibamos su notificación.</p><script src="https://checkout.bold.co/library/boldPaymentButton.js" data-bold-button="{{ $boldButton['buttonStyle'] }}" data-order-id="{{ $boldButton['orderId'] }}" data-currency="{{ $boldButton['currency'] }}" data-amount="{{ $boldButton['amount'] }}" data-api-key="{{ $boldButton['apiKey'] }}" data-integrity-signature="{{ $boldButton['integritySignature'] }}" data-redirection-url="{{ $boldButton['redirectionUrl'] }}" data-description="{{ $boldButton['description'] }}" data-customer-data="{{ $customerData }}"></script><p><a href="{{ route('cart.index') }}">Volver al carrito</a></p></section>
@else
<section class="section"><h1>Finalizar compra</h1><div class="product-detail"><div><h2>Resumen</h2>@foreach($cart->items as $item)<div class="cart-row"><img src="{{ $item->product->images[0] ?? 'https://placehold.co/160x160?text=Producto' }}" alt="{{ $item->product->name }}"><div><strong>{{ $item->product->name }}</strong><p class="muted">{{ $item->quantity }} × ${{ number_format($item->unit_price, 0, ',', '.') }}</p></div><strong>${{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</strong></div>@endforeach<p class="price">Total: ${{ number_format($cart->items->sum(fn ($item) => $item->quantity * $item->unit_price), 0, ',', '.') }} COP</p></div><div class="auth-card" style="margin:0"><h2>Entrega</h2><form method="POST" action="{{ route('checkout.store') }}">@csrf<div class="field"><label for="shipping_address">Dirección de envío</label><textarea class="input" id="shipping_address" name="shipping_address" rows="5" required>{{ old('shipping_address', auth()->user()->shipping_address) }}</textarea>@error('shipping_address')<span class="error">{{ $message }}</span>@enderror</div><button>Continuar a pago seguro</button></form></div></div></section>
@endif
@endsection
