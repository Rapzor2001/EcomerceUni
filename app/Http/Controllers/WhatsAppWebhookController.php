<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): string|JsonResponse
    {
        if ($request->query('hub_verify_token') !== config('services.whatsapp.verify_token')) {
            return response()->json(['message' => 'Invalid verify token'], 403);
        }

        return (string) $request->query('hub_challenge', '');
    }

    public function handle(Request $request): JsonResponse
    {
        // Messages are intentionally logged as metadata only. Persisting or replying
        // to a customer requires an explicitly approved WhatsApp template/workflow.
        Log::info('WhatsApp Cloud webhook received.', ['object' => $request->input('object'), 'entries' => count($request->input('entry', []))]);

        return response()->json(['received' => true]);
    }
}
