<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['required', 'string', 'max:30'], 'shipping_address' => ['required', 'string', 'max:500'], 'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()]]);
        $user = User::create([...$data, 'password' => Hash::make($data['password'])]);
        Auth::login($user);
        $request->session()->regenerate();
        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            Log::error('Unable to send verification email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);
        }
        $request->session()->flash('sync_guest_cart', true);

        return redirect()->route('verification.notice')->with('status', 'Cuenta creada. Revisa tu correo para verificarla; si no llega, puedes solicitar un reenvío.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->flash('sync_guest_cart', true);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            Log::error('Unable to send password reset email.', ['email' => $request->string('email')->toString(), 'exception' => $exception->getMessage()]);

            return back()->with('error', 'No pudimos enviar el correo en este momento. Intenta de nuevo más tarde.');
        }

        return back()->with(
            $status === Password::RESET_LINK_SENT ? 'status' : 'error',
            $status === Password::RESET_LINK_SENT
                ? 'Si existe una cuenta con ese correo, enviamos un enlace seguro para restablecer la contraseña.'
                : 'No pudimos procesar la solicitud. Verifica el correo e inténtalo nuevamente.'
        );
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión con tu nueva contraseña.')
            : back()->withErrors(['email' => ['El enlace no es válido o ya venció. Solicita uno nuevo.']]);
    }
}
