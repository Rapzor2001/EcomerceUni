@extends('layouts.app')
@section('content')
<section class="section"><h1>Tu carrito</h1><p class="muted">{{ auth()->check() ? 'Tu carrito está guardado en tu cuenta.' : 'Inicia sesión para guardar el carrito en todos tus dispositivos.' }}</p><div data-cart-root><p>Cargando carrito…</p></div>@auth @if(!auth()->user()->hasVerifiedEmail())<p class="alert">Debes <a href="{{ route('verification.notice') }}"><strong>verificar tu correo</strong></a> antes de realizar el pago.</p>@endif @endauth</section>
@endsection
