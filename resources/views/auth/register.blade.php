@extends('layouts.app')
@section('content')
<section class="auth-card">
    <h1>Crea tu cuenta</h1>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="field"><label for="name">Nombre completo</label><input class="input" id="name" name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="email">Correo electrónico</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel">@error('phone')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="shipping_address">Dirección de envío</label><input class="input" id="shipping_address" name="shipping_address" value="{{ old('shipping_address') }}" required autocomplete="street-address">@error('shipping_address')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="password">Contraseña</label><input class="input" id="password" name="password" type="password" required autocomplete="new-password">@error('password')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirma tu contraseña</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button>Crear cuenta</button>
    </form>
    <p>¿Ya tienes cuenta? <a href="{{ route('login') }}">Ingresar</a></p>
</section>
@endsection
