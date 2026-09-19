<?php

/**
 * File: Accepts external events exactly once per provider key and detects key misuse.
 * Symbols: WebhookService and receive().
 * State: canonical payload hash and persisted replay response.
 */

namespace App\Services;

use App\Models\User;
use App\Models\WebhookReceipt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WebhookService
{
    /** Persist a webhook response or return the original response for a safe replay. */
    public function receive(User $actor, string $provider, string $eventId, array $payload): array
    {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $provider, $eventId, $payload, $hash): array {
            $existing = WebhookReceipt::query()
                ->where('tenant_id', $actor->tenant_id)
                ->where('provider', $provider)
                ->where('external_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The event key was already used with a different payload.');
                }

                return $existing->response + ['replayed' => true];
            }

            $response = ['accepted' => true, 'event_id' => $eventId, 'payload_fields' => count($payload)];
            WebhookReceipt::query()->create([
                'tenant_id' => $actor->tenant_id,
                'provider' => $provider,
                'external_event_id' => $eventId,
                'payload_hash' => $hash,
                'response' => $response,
            ]);

            return $response + ['replayed' => false];
        });
    }
}
