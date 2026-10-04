<?php

namespace App\Services;

use App\Models\Order;
use LogicException;

class BoldPaymentService
{
    /** Bold requires SHA-256(order-id + integer amount + currency + integrity key). */
    public function integritySignature(Order $order): string
    {
        return hash('sha256', $order->provider_reference.$this->amountForBold($order).config('bold.currency').config('bold.integrity_key'));
    }

    public function amountForBold(Order $order): int
    {
        return (int) round((float) $order->total);
    }

    public function webhookIsValid(string $rawPayload, ?string $signature): bool
    {
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', base64_encode($rawPayload), (string) config('bold.webhook_secret'));

        return hash_equals($expected, $signature);
    }

    public function buttonData(Order $order): array
    {
        $redirectionUrl = $this->redirectionUrl($order->public_id);
        $this->assertCheckoutConfiguration($redirectionUrl);

        return [
            'apiKey' => (string) config('bold.identity_key'),
            'orderId' => $order->provider_reference,
            'amount' => $this->amountForBold($order),
            'currency' => config('bold.currency'),
            'integritySignature' => $this->integritySignature($order),
            'redirectionUrl' => $redirectionUrl,
            'description' => "Pedido {$order->provider_reference}",
            'buttonStyle' => config('bold.button_style'),
        ];
    }

    /** Return a readable configuration error before sending a customer to Bold. */
    public function configurationError(string $redirectionUrl): ?string
    {
        if (blank(config('bold.identity_key'))) {
            return 'Falta configurar la llave de identidad de Bold.';
        }

        if (blank(config('bold.integrity_key'))) {
            return 'Falta la llave secreta de integridad de Bold. No uses la llave de identidad en este campo.';
        }

        if (! filter_var($redirectionUrl, FILTER_VALIDATE_URL) || parse_url($redirectionUrl, PHP_URL_SCHEME) !== 'https') {
            return 'Bold requiere una URL de retorno pública que comience por https://. Configura BOLD_REDIRECTION_URL antes de cobrar.';
        }

        return null;
    }

    public function redirectionUrl(string $orderPublicId): string
    {
        $configuredUrl = config('bold.redirection_url');

        if (filled($configuredUrl)) {
            return str_replace('{order}', $orderPublicId, (string) $configuredUrl);
        }

        return route('checkout.success', ['order' => $orderPublicId]);
    }

    public function assertCheckoutConfiguration(string $redirectionUrl): void
    {
        if ($error = $this->configurationError($redirectionUrl)) {
            throw new LogicException($error);
        }
    }
}
