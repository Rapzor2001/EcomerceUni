@extends('layouts.app')
@section('content')
<section class="auth-card"><h1>Ingresar</h1><form method="POST">@csrf<div class="field"><label>Correo electrónico</label><input class="input" name="email" type="email" value="{{ old('email') }}" required autofocus>@error('email')<span class="error">{{ $message }}</span>@enderror</div><div class="field"><label>Contraseña</label><input class="input" name="password" type="password" required></div><label><input name="remember" type="checkbox"> Recordarme</label><p><button>Ingresar</button></p></form><p><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></p><p>¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate</a></p></section>
@endsection
