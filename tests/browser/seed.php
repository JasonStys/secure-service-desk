<?php

/**
 * File: Seeds independently known SLA/tenant/history fixtures through real application services.
 * Symbols: fixtureTicket helper and isolated fixture manifest assembly.
 * State: disposable synthetic users/tickets only; the emitted manifest has no keys or session tokens.
 */

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$agent = User::query()->where('email', 'agent@northwind.example')->firstOrFail();
$requester = User::query()->where('email', 'requester@northwind.example')->firstOrFail();
$outsider = User::query()->where('email', 'agent@contoso.example')->firstOrFail();
$additional = [];
foreach ([['avery@northwind.example', 'Avery Reviewer', Role::Agent],
    ['other-requester@northwind.example', 'Jordan Other Requester', Role::Requester]] as [$email, $name, $role]) {
    $additional[] = User::query()->create(['tenant_id' => $agent->tenant_id, 'email' => $email,
        'name' => $name, 'role' => $role, 'password' => Hash::make(bin2hex(random_bytes(20)))]);
}
$service = app(TicketService::class);

/** Construct ordinary work through the production service; only synthetic fixture due dates are adjusted. */
function fixtureTicket(User $requester, string $title): Ticket
{
    return app(TicketService::class)->create($requester, ['title' => $title,
        'description' => 'Synthetic field-service request with reviewed reference measurements.',
        'priority' => 'normal', 'request_key' => 'browser-seed-'.bin2hex(random_bytes(12))])->refresh();
}

$overdue = fixtureTicket($requester, 'Fixture SLA overdue');
$service->assign($agent, $overdue, $agent->id, $overdue->version);
$overdue->refresh()->forceFill(['sla_due_at' => now()->subMinutes(30)])->save();
$soon = fixtureTicket($requester, 'Fixture SLA due soon');
$soon->forceFill(['sla_due_at' => now()->addHours(2)])->save();
$resolved = fixtureTicket($requester, 'Fixture SLA resolved');
$resolved = $service->transition($agent, $resolved, TicketStatus::Open, $resolved->version);
$resolved = $service->transition($agent, $resolved, TicketStatus::Resolved, $resolved->version);
$resolved->forceFill(['sla_due_at' => now()->subHour()])->save();
$private = fixtureTicket($additional[1], 'Other requester private fixture');
$outside = fixtureTicket($outsider, 'Other tenant private fixture');
$history = fixtureTicket($requester, 'Fixture bounded history');
for ($index = 1; $index <= 21; $index++) {
    $service->addComment($requester, $history, sprintf('Inspection note %02d: reference reading reviewed and recorded.', $index));
}
echo json_encode(['users' => ['agent' => $agent->id, 'requester' => $requester->id,
    'outsider' => $outsider->id, 'avery' => $additional[0]->id, 'other_requester' => $additional[1]->id],
    'tickets' => ['overdue' => $overdue->id, 'soon' => $soon->id, 'resolved' => $resolved->id,
        'private' => $private->id, 'outside' => $outside->id, 'history' => $history->id]], JSON_THROW_ON_ERROR).PHP_EOL;
