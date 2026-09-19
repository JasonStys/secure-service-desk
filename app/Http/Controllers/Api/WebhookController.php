<?php

/**
 * File: Authenticates and records idempotent inbound webhook events.
 * Symbols: WebhookController and __invoke().
 * State: signature, provider, event id, and JSON payload; exact locations are in docs/code-index.md.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /** Inject the webhook receipt service. */
    public function __construct(private readonly WebhookService $webhooks) {}

    /** Verify the HMAC before accepting or replaying an event. */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.webhooks.secret');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Webhook-Signature')), 401, 'Invalid signature.');

        $data = $request->validate([
            'provider' => ['required', 'string', 'max:80'],
            'event_id' => ['required', 'string', 'max:160'],
            'payload' => ['required', 'array', 'max:50'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');

        return response()->json($this->webhooks->receive(
            $actor,
            $data['provider'],
            $data['event_id'],
            $data['payload'],
        ));
    }
}
