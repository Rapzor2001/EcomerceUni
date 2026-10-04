@extends('layouts.app')
@section('content')
<section class="auth-card"><h1>Recupera tu contraseña</h1><p>Te enviaremos un enlace seguro. Por tu seguridad, el mensaje será igual aunque el correo no esté registrado.</p><form method="POST" action="{{ route('password.email') }}">@csrf<div class="field"><label for="email">Correo electrónico</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>@error('email')<span class="error">{{ $message }}</span>@enderror</div><button>Enviar enlace</button></form></section>
@endsection
