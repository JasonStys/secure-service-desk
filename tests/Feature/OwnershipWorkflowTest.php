<?php

/**
 * File: Independent ownership/queue/detail acceptance for Jason Stys's synthetic service desk.
 * Symbols: OwnershipWorkflowTest, ticket fixtures and role/tenant/version/boundary cases.
 * State: disposable database, fixed clock and expected audit/outbox counts; docs/code-index.md.
 */

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\AuditEntry;
use App\Models\OutboxMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaEscalationService;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnershipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** Create realistic ordinary work, never bypassing creation invariants. */
    private function ticket(User $requester, string $title = 'Field service export'): Ticket
    {
        return app(TicketService::class)->create($requester, [
            'title' => $title, 'description' => 'Customer report missing a scheduled export.',
            'priority' => 'high', 'request_key' => 'case-'.fake()->uuid(),
        ]);
    }

    /** Assignment writes state/audit/outbox atomically and preserves SLA, content and status. */
    public function test_assignment_unassignment_and_noop_preserve_business_invariants(): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester']);
        $original = $ticket->only(['status', 'priority', 'sla_due_at', 'title', 'description']);
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/assignment", ['assignee_id' => $actors['agent']->id, 'version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.assignee_id', $actors['agent']->id);
        $this->assertDatabaseCount('audit_entries', 2);
        $this->assertDatabaseCount('outbox_messages', 2);
        $ticket->refresh();
        self::assertEquals($original, $ticket->only(array_keys($original)));
        $event = AuditEntry::query()->latest('id')->firstOrFail();
        self::assertSame('ticket.assigned', $event->event);
        self::assertSame(['from_assignee_id' => null, 'to_assignee_id' => $actors['agent']->id, 'version' => 2], $event->metadata);
        app(TicketService::class)->assign($actors['agent'], $ticket, $actors['agent']->id, 2);
        $this->assertDatabaseCount('audit_entries', 2);
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/assignment", ['assignee_id' => null, 'version' => 2])
            ->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.assignee_id', null);
        $this->assertDatabaseCount('audit_entries', 3);
    }

    /** Concurrent lifecycle/assignment writes share the same version; rejected requests leave no evidence. */
    public function test_stale_or_ineligible_assignment_does_not_write(): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester']);
        app(TicketService::class)->transition($actors['agent'], $ticket, TicketStatus::Open, 1);
        foreach ([['assignee_id' => $actors['agent']->id, 'version' => 1],
            ['assignee_id' => $actors['requester']->id, 'version' => 2],
            ['assignee_id' => $actors['outsider']->id, 'version' => 2],
            ['assignee_id' => 999999, 'version' => 2], ['version' => 2],
            ['assignee_id' => -1, 'version' => 2], ['assignee_id' => [], 'version' => 2]] as $payload) {
            $this->withHeader('X-Demo-Actor', $actors['agent']->email)
                ->postJson("/api/v1/tickets/{$ticket->id}/assignment", $payload)->assertUnprocessable();
        }
        self::assertNull($ticket->refresh()->assignee_id);
        self::assertSame(2, $ticket->version);
        $this->assertDatabaseCount('audit_entries', 2);
        $this->assertDatabaseCount('outbox_messages', 2);
    }

    /** Requesters cannot assign; another tenant cannot view or mutate even known ticket identifiers. */
    public function test_roles_and_tenants_conceal_inaccessible_workspaces(): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester']);
        $url = "/tickets/{$ticket->id}";
        $this->withHeader('X-Demo-Actor', $actors['requester']->email)->get($url)
            ->assertOk()->assertDontSee('Save assignment')->assertSee('Discussion');
        $this->withHeader('X-Demo-Actor', $actors['requester']->email)
            ->postJson("/api/v1{$url}/assignment", ['assignee_id' => $actors['agent']->id, 'version' => 1])->assertForbidden();
        $this->withHeader('X-Demo-Actor', $actors['outsider']->email)->get($url)->assertNotFound();
        $this->withHeader('X-Demo-Actor', $actors['outsider']->email)
            ->postJson("/api/v1{$url}/assignment", ['assignee_id' => $actors['outsider']->id, 'version' => 1])->assertNotFound();
        $actors['agent']->forceFill(['role' => Role::Requester])->save();
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)->get($url)->assertNotFound();
    }

    /** Resolved/closed work cannot quietly acquire a new owner; reopening preserves explicit workflow. */
    public function test_resolved_and_closed_work_require_correct_lifecycle(): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester']);
        $service = app(TicketService::class);
        $service->transition($actors['agent'], $ticket, TicketStatus::Open, 1);
        $service->transition($actors['agent'], $ticket, TicketStatus::Resolved, 2);
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/assignment", ['assignee_id' => $actors['agent']->id, 'version' => 3])->assertUnprocessable();
        $service->transition($actors['agent'], $ticket, TicketStatus::Open, 3);
        $assigned = $service->assign($actors['agent'], $ticket, $actors['agent']->id, 4);
        self::assertSame(5, $assigned->version);
        $service->transition($actors['agent'], $ticket, TicketStatus::Closed, 5);
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/assignment", ['assignee_id' => null, 'version' => 6])->assertUnprocessable();
    }

    /** Fixed UTC boundary oracle distinguishes due-now, next24h and resolved work under owner filters. */
    public function test_queue_filters_combine_owner_deadline_and_existing_access(): void
    {
        Carbon::setTestNow('2026-11-09T12:00:00Z');
        try {
            $actors = $this->createActors();
            $due = $this->ticket($actors['requester'], 'Due exactly now');
            $due->forceFill(['sla_due_at' => now(), 'assignee_id' => $actors['agent']->id])->save();
            $soon = $this->ticket($actors['requester'], 'Due exactly24hours');
            $soon->forceFill(['sla_due_at' => now()->addHours(24)])->save();
            $later = $this->ticket($actors['requester'], 'Later than window');
            $later->forceFill(['sla_due_at' => now()->addHours(24)->addSecond()])->save();
            $resolved = $this->ticket($actors['requester'], 'Resolved overdue');
            $resolved->forceFill(['status' => TicketStatus::Resolved, 'sla_due_at' => now()->subHour()])->save();
            $this->ticket($actors['outsider'], 'Other tenant private');
            $this->withHeader('X-Demo-Actor', $actors['agent']->email)->get('/?owner=mine&due=overdue')
                ->assertOk()->assertSee('Due exactly now')->assertDontSee('Due exactly24hours')
                ->assertDontSee('Resolved overdue')->assertDontSee('Other tenant private');
            $this->get('/?owner=unassigned&due=due-soon')->assertOk()->assertSee('Due exactly24hours')
                ->assertDontSee('Due exactly now')->assertDontSee('Later than window');
            $this->getJson('/?owner=123')->assertUnprocessable();
            $this->getJson('/?due=invalid')->assertUnprocessable();
            $this->getJson('/?page=0')->assertUnprocessable();
        } finally {
            Carbon::setTestNow();
        }
    }

    /** Independent paginated history stays bounded, escapes hostile content and retains actor query. */
    public function test_discussion_and_audit_history_are_bounded_and_escaped(): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester'], '<script>hostile()</script>');
        for ($index = 0; $index < 23; $index++) {
            app(TicketService::class)->addComment($actors['requester'], $ticket, "note-{$index} <img src=x onerror=bad()>");
        }
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertViewHas('comments', fn ($page) => count($page->items()) === 20 && $page->hasMorePages())
            ->assertViewHas('audit', fn ($page) => count($page->items()) === 20 && $page->hasMorePages())
            ->assertSee('&lt;script&gt;hostile()&lt;/script&gt;', false)->assertDontSee('<script>hostile()</script>', false)
            ->assertDontSee('<img src=x onerror=bad()>', false)->assertSee('Older comments');
        $this->get("/tickets/{$ticket->id}?comments_page=2&audit_page=2&actor=".urlencode($actors['agent']->email))
            ->assertOk()->assertViewHas('comments', fn ($page) => count($page->items()) === 3)
            ->assertViewHas('audit', fn ($page) => count($page->items()) === 4)->assertSee('Newer comments');
        $this->getJson("/tickets/{$ticket->id}?audit_page=-1")->assertUnprocessable();
    }

    /** Optional blank tags must not prevent a real user's ordinary browser submission. */
    public function test_browser_can_create_ticket_with_unused_optional_tag_field(): void
    {
        $actors = $this->createActors();
        $this->withHeader('X-Demo-Actor', $actors['requester']->email)->post('/tickets', [
            'title' => 'No tags needed', 'description' => 'A realistic simple request.',
            'priority' => 'normal', 'tags' => [''], 'request_key' => 'blank-tags',
        ])->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame([], Ticket::query()->firstOrFail()->tags);
        self::assertSame(1, OutboxMessage::query()->count());
    }

    /** Verify each independently changed candidate is rechecked after selection and before mutation. */
    #[DataProvider('slaChanges')]
    public function test_sla_rechecks_candidates_after_an_interleaved_change(string $change): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester']);
        $ticket->forceFill(['sla_due_at' => now()->subHour()])->save();
        $connection = DB::connection();
        $original = $connection->getEventDispatcher();
        $events = clone $original;
        $connection->setEventDispatcher($events);
        $interleaved = false;
        $events->listen(QueryExecuted::class, function (QueryExecuted $query) use ($ticket, $change, &$interleaved): void {
            if (! $interleaved && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'sla_breached_at')) {
                $interleaved = true;
                DB::table('tickets')->where('id', $ticket->id)->update(match ($change) {
                    'resolved' => ['status' => 'resolved'],
                    'closed' => ['status' => 'closed'],
                    'extended' => ['sla_due_at' => now()->addDay()],
                });
            }
        });
        try {
            self::assertSame(0, app(SlaEscalationService::class)->escalateDue());
            self::assertTrue($interleaved, 'The independent fixture must change a selected candidate.');
            self::assertNull($ticket->refresh()->sla_breached_at);
            $this->assertDatabaseCount('audit_entries', 1);
            $this->assertDatabaseCount('outbox_messages', 1);
        } finally {
            $connection->setEventDispatcher($original);
        }
    }

    /** Enumerate different invalidation causes rather than sleeping to simulate a race. */
    public static function slaChanges(): array
    {
        return [['resolved'], ['closed'], ['extended']];
    }

    /** A tenant admin can manage work; a capped selector must never silently substitute Unassigned. */
    public function test_admin_and_current_owner_outside_the_selector_window(): void
    {
        $actors = $this->createActors();
        $actors['agent']->forceFill(['role' => Role::Admin])->save();
        $ticket = $this->ticket($actors['requester']);
        app(TicketService::class)->assign($actors['agent'], $ticket, $actors['agent']->id, 1);
        $rows = [];
        for ($index = 0; $index < 201; $index++) {
            $rows[] = ['tenant_id' => $actors['tenant']->id, 'name' => sprintf('AAA agent%03d', $index),
                'email' => "agent{$index}@fixture.example", 'role' => 'agent', 'password' => $actors['agent']->password];
        }
        DB::table('users')->insert($rows);
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertViewHas('agents', fn ($agents) => $agents->count() === 201)
            ->assertSee('Showing the first 200 eligible agents')->assertSee('current owner; eligibility rechecked on save')
            ->assertSee('value="'.$actors['agent']->id.'" selected', false);
        self::assertSame($actors['agent']->id, $ticket->refresh()->assignee_id);
    }

    /** Notification-intent failure must roll back ownership and audit, not leave a partial commit. */
    public function test_assignment_rolls_back_when_its_outbox_insert_fails(): void
    {
        $actors = $this->createActors();
        $ticket = $this->ticket($actors['requester']);
        $original = OutboxMessage::getEventDispatcher();
        $events = clone $original;
        OutboxMessage::setEventDispatcher($events);
        $events->listen('eloquent.creating: '.OutboxMessage::class, function (): never {
            throw new \RuntimeException('Independent outbox failure fixture');
        });
        try {
            try {
                app(TicketService::class)->assign($actors['agent'], $ticket, $actors['agent']->id, 1);
                self::fail('The independent delivery-intent fixture must abort this transaction.');
            } catch (\RuntimeException $error) {
                self::assertSame('Independent outbox failure fixture', $error->getMessage());
            }
            self::assertNull($ticket->refresh()->assignee_id);
            self::assertSame(1, $ticket->version);
            $this->assertDatabaseCount('audit_entries', 1);
            $this->assertDatabaseCount('outbox_messages', 1);
        } finally {
            OutboxMessage::setEventDispatcher($original);
        }
    }

    /** A stale route-bound model must not authorize writes after persisted ownership changes. */
    public function test_mutations_reauthorize_the_locked_current_ticket(): void
    {
        $actors = $this->createActors();
        $stale = $this->ticket($actors['requester']);
        DB::table('tickets')->where('id', $stale->id)->update([
            'tenant_id' => $actors['outsider']->tenant_id, 'requester_id' => $actors['outsider']->id,
        ]);
        $service = app(TicketService::class);
        $operations = [
            fn () => $service->assign($actors['agent'], $stale, $actors['agent']->id, 1),
            fn () => $service->transition($actors['agent'], $stale, TicketStatus::Open, 1),
            fn () => $service->addComment($actors['agent'], $stale, 'Must not cross the new access boundary'),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('Persisted ticket authorization must be rechecked under its write lock.');
            } catch (ModelNotFoundException) {
                self::assertTrue(true);
            }
        }
        $current = Ticket::query()->findOrFail($stale->id);
        self::assertNull($current->assignee_id);
        self::assertSame(1, $current->version);
        self::assertSame(TicketStatus::New, $current->status);
        $this->assertDatabaseCount('ticket_comments', 0);
        $this->assertDatabaseCount('audit_entries', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
    }

    /** First-create JSON must include the same persisted defaults that a later replay reads. */
    public function test_first_create_response_contains_persisted_version_and_ownership(): void
    {
        $actors = $this->createActors();
        $payload = ['title' => 'New request API contract', 'description' => 'Must be assignable immediately.',
            'priority' => 'normal', 'request_key' => 'first-defaults'];
        $response = $this->withHeader('X-Demo-Actor', $actors['requester']->email)
            ->postJson('/api/v1/tickets', $payload)->assertCreated()->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'new')->assertJsonPath('data.assignee_id', null);
        $first = $response->json('data');
        self::assertArrayHasKey('assignee_id', $first);
        self::assertSame(Ticket::query()->findOrFail($first['id'])->toArray(), $first);
        $this->postJson('/api/v1/tickets', $payload)->assertCreated()->assertExactJson(['data' => $first]);
    }

    /** Exercise actual SQLite literal matching even when the main CI suite uses PostgreSQL. */
    public function test_sqlite_literal_search_matches_metacharacters_without_broadening_results(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.literal_fixture' => ['driver' => 'sqlite', 'database' => ':memory:',
            'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('literal_fixture');
        try {
            self::assertSame(0, Artisan::call('migrate', ['--database' => 'literal_fixture', '--force' => true]));
            $actors = $this->createActors();
            $cases = ['%' => 'Percent%dispatch', '_' => 'Under_score', '!' => 'Bang!control', '\\' => 'Back\\slash'];
            foreach ($cases as $title) {
                $this->ticket($actors['requester'], $title);
            }
            $this->ticket($actors['requester'], 'Ordinary work');
            foreach ($cases as $query => $title) {
                $response = $this->withHeader('X-Demo-Actor', $actors['agent']->email)->get('/?q='.urlencode($query));
                $response->assertOk()->assertViewHas('tickets', fn ($page) => $page->total() === 1
                    && $page->first()->title === $title);
            }
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('literal_fixture');
        }
    }
}
