@extends('layouts.app')
@section('content')
<section class="auth-card"><h1>Verifica tu correo</h1><p>Enviamos un enlace de verificación a <strong>{{ auth()->user()->email }}</strong>. Ábrelo para habilitar tus compras.</p><form method="POST" action="{{ route('verification.send') }}">@csrf<button>Reenviar enlace</button></form></section>
@endsection
