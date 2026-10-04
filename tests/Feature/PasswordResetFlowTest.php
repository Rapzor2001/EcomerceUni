<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('Original123!')]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'Si existe una cuenta con ese correo, enviamos un enlace seguro para restablecer la contraseña.');
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Contraseña actualizada. Ya puedes iniciar sesión con tu nueva contraseña.');

        $this->assertTrue(Hash::check('NuevaClave123!', $user->fresh()->password));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'NuevaClave123!'])->assertRedirect(route('home'));
    }

    public function test_password_reset_rejects_an_invalid_or_weak_password(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.update'), ['token' => 'invalid-token', 'email' => $user->email, 'password' => 'corta', 'password_confirmation' => 'distinta'])
            ->assertSessionHasErrors(['password']);
    }
}
