<?php

namespace App\Providers;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make('mail.manager')->extend('resend-secure', function (array $config): ResendTransport {
            $apiKey = (string) config('services.resend.key');
            $httpClient = new GuzzleClient([
                'verify' => CaBundle::getBundledCaBundlePath(),
                'timeout' => (float) ($config['timeout'] ?? 15),
            ]);
            $transporter = new HttpTransporter(
                $httpClient,
                BaseUri::from('api.resend.com'),
                Headers::withAuthorization(ApiKey::from($apiKey)),
            );

            return new ResendTransport(new Client($transporter));
        });

        if ($this->app->environment('local') && filled($address = config('mail.redirect_to'))) {
            Mail::alwaysTo($address);
        }
    }
}
