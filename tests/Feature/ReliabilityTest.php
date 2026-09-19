<?php

/**
 * File: Verifies webhook idempotency, outbox retry/dead-letter handling, and SLA escalation.
 * Symbols: ReliabilityTest and four reliability/security tests.
 * State: frozen clock, persisted receipts, messages, and escalation records.
 */

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Services\OutboxProcessor;
use App\Services\SlaEscalationService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    /** Verify valid signed events replay safely while key reuse with changed content conflicts. */
    public function test_webhook_signature_and_idempotency_contract(): void
    {
        $actors = $this->createActors();
        $body = json_encode(['provider' => 'monitor', 'event_id' => 'evt-101', 'payload' => ['healthy' => true]], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'local-demo-secret-change-me');
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DEMO_ACTOR' => $actors['agent']->email,
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/api/v1/webhooks/ticket-events', [], [], [], $server, $body)
            ->assertOk()->assertJsonPath('replayed', false);
        $this->call('POST', '/api/v1/webhooks/ticket-events', [], [], [], $server, $body)
            ->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('webhook_receipts', 1);

        $changed = json_encode(['provider' => 'monitor', 'event_id' => 'evt-101', 'payload' => ['healthy' => false]], JSON_THROW_ON_ERROR);
        $server['HTTP_X_WEBHOOK_SIGNATURE'] = hash_hmac('sha256', $changed, 'local-demo-secret-change-me');
        $this->call('POST', '/api/v1/webhooks/ticket-events', [], [], [], $server, $changed)->assertConflict();
    }

    /** Verify invalid webhook signatures are rejected before a receipt is stored. */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $actors = $this->createActors();
        $this->withHeaders(['X-Demo-Actor' => $actors['agent']->email, 'X-Webhook-Signature' => 'invalid'])
            ->postJson('/api/v1/webhooks/ticket-events', [
                'provider' => 'monitor', 'event_id' => 'evt-102', 'payload' => ['healthy' => true],
            ])->assertUnauthorized();
        $this->assertDatabaseCount('webhook_receipts', 0);
    }

    /** Verify three delivery failures produce an inspectable dead-letter record. */
    public function test_outbox_retries_then_dead_letters(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00');
        $actors = $this->createActors();
        app(TicketService::class)->create($actors['requester'], [
            'title' => 'Notify me', 'description' => 'Exercise delivery failure handling.',
            'priority' => 'normal', 'request_key' => 'outbox-1',
        ]);
        $processor = app(OutboxProcessor::class);

        for ($attempt = 1; $attempt <= OutboxProcessor::MAX_ATTEMPTS; $attempt++) {
            OutboxMessage::query()->update(['available_at' => now()->subSecond()]);
            $processor->process(fn () => throw new RuntimeException(str_repeat('temporary failure ', 50)));
        }

        $message = OutboxMessage::firstOrFail();
        self::assertSame('dead_letter', $message->status);
        self::assertSame(3, $message->attempts);
        self::assertLessThanOrEqual(500, strlen($message->last_error));
        Carbon::setTestNow();
    }

    /** Verify overdue tickets escalate once and create one matching audit/outbox pair. */
    public function test_sla_escalation_is_idempotent(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00');
        $actors = $this->createActors();
        $ticket = app(TicketService::class)->create($actors['requester'], [
            'title' => 'Overdue request', 'description' => 'Exercise the scheduler.',
            'priority' => 'critical', 'request_key' => 'sla-1',
        ]);
        $ticket->forceFill(['sla_due_at' => now()->subMinute()])->save();

        $service = app(SlaEscalationService::class);
        self::assertSame(1, $service->escalateDue());
        self::assertSame(0, $service->escalateDue());
        $this->assertDatabaseHas('audit_entries', ['ticket_id' => $ticket->id, 'event' => 'ticket.sla_breached']);
        $this->assertDatabaseHas('outbox_messages', ['tenant_id' => $ticket->tenant_id, 'topic' => 'ticket.sla_breached']);
        Carbon::setTestNow();
    }
}
