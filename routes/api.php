<?php

/**
 * File: Versioned JSON API routes with actor resolution and rate limiting.
 * Symbols: ticket listing/creation/transitions and idempotent inbound webhook.
 * State: route parameters only; exact declarations are indexed in docs/code-index.md.
 */

use App\Http\Controllers\Api\TicketApiController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['demo.actor', 'throttle:api'])->group(function (): void {
    Route::get('/tickets', [TicketApiController::class, 'index']);
    Route::post('/tickets', [TicketApiController::class, 'store']);
    Route::post('/tickets/{ticket}/transition', [TicketApiController::class, 'transition']);
    Route::post('/webhooks/ticket-events', WebhookController::class);
});
