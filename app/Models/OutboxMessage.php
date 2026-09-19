<?php

/**
 * File: Persists reliable notification work in the same transaction as ticket changes.
 * Symbols: OutboxMessage model and retry-state casts.
 * State: deduplication key, topic, payload, status, attempts, schedule, error, and delivery timestamps.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    /** @var list<string> Outbox fields managed by domain and delivery services. */
    protected $fillable = [
        'tenant_id', 'deduplication_key', 'topic', 'payload', 'status',
        'attempts', 'available_at', 'last_error', 'delivered_at',
    ];

    /** @return array<string, string> Runtime type conversions for message state. */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
