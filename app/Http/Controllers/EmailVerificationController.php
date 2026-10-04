<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }
        $request->fulfill();

        return redirect()->route('home')->with('status', 'Correo verificado correctamente.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }
        try {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('status', 'Te enviamos un nuevo enlace de verificación.');
        } catch (\Throwable $exception) {
            Log::error('Unable to resend verification email.', ['user_id' => $request->user()->id, 'exception' => $exception->getMessage()]);

            return back()->with('error', 'No pudimos enviar el correo en este momento. Intenta de nuevo más tarde.');
        }
    }
}
