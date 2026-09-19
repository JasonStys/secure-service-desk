<?php

/**
 * File: Delivers pending outbox messages with bounded batches and exponential retry delays.
 * Symbols: OutboxProcessor, process(), and processOne().
 * State: message delivery status, attempts, availability, error, and delivery timestamp.
 */

namespace App\Services;

use App\Models\OutboxMessage;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OutboxProcessor
{
    public const MAX_ATTEMPTS = 3;

    /** Deliver at most the requested number of ready messages. */
    public function process(Closure $deliver, int $limit = 25): int
    {
        $ids = OutboxMessage::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)))
            ->pluck('id');

        foreach ($ids as $id) {
            $this->processOne((int) $id, $deliver);
        }

        return $ids->count();
    }

    /** Lock and attempt one message, recording success, retry, or dead-letter state. */
    private function processOne(int $id, Closure $deliver): void
    {
        DB::transaction(function () use ($id, $deliver): void {
            $message = OutboxMessage::query()->lockForUpdate()->find($id);
            if ($message === null || $message->status !== 'pending' || $message->available_at->isFuture()) {
                return;
            }

            try {
                $deliver($message);
                $message->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'last_error' => null])->save();
            } catch (Throwable $error) {
                $attempts = $message->attempts + 1;
                $message->forceFill([
                    'attempts' => $attempts,
                    'status' => $attempts >= self::MAX_ATTEMPTS ? 'dead_letter' : 'pending',
                    'available_at' => now()->addSeconds(2 ** $attempts),
                    'last_error' => Str::limit($error->getMessage(), 500, ''),
                ])->save();
            }
        });
    }
}
