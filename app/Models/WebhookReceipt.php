<?php

/**
 * File: Records processed webhook keys so repeated deliveries are harmless.
 * Symbols: WebhookReceipt model.
 * State: tenant, provider, external event id, payload hash, and response; see docs/code-index.md.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookReceipt extends Model
{
    /** @var list<string> Receipt fields written after validating an event. */
    protected $fillable = ['tenant_id', 'provider', 'external_event_id', 'payload_hash', 'response'];

    /** @return array<string, string> Runtime type conversions for stored responses. */
    protected function casts(): array
    {
        return ['response' => 'array'];
    }
}
