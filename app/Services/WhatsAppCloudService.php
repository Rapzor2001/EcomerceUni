<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudService
{
    public function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function purchaseConfirmed(Order $order): void
    {
        $this->sendTemplate($order, 'purchase_confirmed', [$order->provider_reference, number_format((float) $order->total, 0, ',', '.')]);
    }

    public function shipmentCreated(Order $order): void
    {
        $this->sendTemplate($order, 'shipment_tracking', [$order->provider_reference, $order->shipping_carrier, $order->tracking_number]);
    }

    public function chatUrl(Order $order): string
    {
        $number = preg_replace('/\D+/', '', (string) $order->user?->phone);
        $body = rawurlencode("Hola {$order->user?->name}, te contactamos por tu pedido {$order->provider_reference} de NOIR DISTRICT.");

        return "https://wa.me/57{$number}?text={$body}";
    }

    private function sendTemplate(Order $order, string $template, array $parameters): void
    {
        if (! $this->configured() || ! $order->user?->phone) return;

        try {
            Http::withToken(config('services.whatsapp.token'))
                ->post('https://graph.facebook.com/'.config('services.whatsapp.version').'/'.config('services.whatsapp.phone_number_id').'/messages', [
                    'messaging_product' => 'whatsapp', 'to' => preg_replace('/\D+/', '', $order->user->phone), 'type' => 'template',
                    'template' => ['name' => $template, 'language' => ['code' => config('services.whatsapp.language')], 'components' => [['type' => 'body', 'parameters' => collect($parameters)->map(fn ($value) => ['type' => 'text', 'text' => (string) $value])->all()]]],
                ])->throw();
        } catch (\Throwable $exception) {
            Log::warning('WhatsApp Cloud notification failed.', ['order' => $order->id, 'template' => $template, 'error' => $exception->getMessage()]);
        }
    }
}
