<?php

/**
 * File: Loads two synthetic tenants and a small, non-sensitive demonstration dataset.
 * Symbols: DatabaseSeeder and run().
 * State: tenant, actor, ticket, comment, audit, and outbox demonstration records.
 */

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** Seed synthetic data that demonstrates isolation without using personal information. */
    public function run(): void
    {
        $northwind = Tenant::query()->firstOrCreate(['slug' => 'northwind'], ['name' => 'Northwind Field Services']);
        $contoso = Tenant::query()->firstOrCreate(['slug' => 'contoso'], ['name' => 'Contoso Research']);

        $agent = User::query()->firstOrCreate(['email' => 'agent@northwind.example'], [
            'tenant_id' => $northwind->id,
            'name' => 'Morgan Agent',
            'password' => $this->referencePassword(),
            'role' => Role::Agent,
        ]);
        $requester = User::query()->firstOrCreate(['email' => 'requester@northwind.example'], [
            'tenant_id' => $northwind->id,
            'name' => 'Riley Requester',
            'password' => $this->referencePassword(),
            'role' => Role::Requester,
        ]);
        User::query()->firstOrCreate(['email' => 'agent@contoso.example'], [
            'tenant_id' => $contoso->id,
            'name' => 'Casey Other Tenant',
            'password' => $this->referencePassword(),
            'role' => Role::Agent,
        ]);

        $service = app(TicketService::class);
        $ticket = $service->create($requester, [
            'title' => 'Telemetry export is delayed',
            'description' => 'The scheduled export completed but arrived outside the expected window.',
            'priority' => 'high',
            'tags' => ['telemetry', 'reporting'],
            'request_key' => 'seed-ticket-001',
        ]);
        if (! $ticket->comments()->exists()) {
            $service->addComment($agent, $ticket, 'Acknowledged. Reviewing the export worker metrics now.');
        }
    }

    /** Native reference actors get fresh unusable credentials; existing development fixture behavior stays explicit. */
    private function referencePassword(): string
    {
        return Hash::make(app()->environment('native-local') ? bin2hex(random_bytes(32)) : 'local-demo-only');
    }
}
