<?php

/**
 * File: Defines reliable outbox processing and scheduled SLA escalation commands.
 * Symbols: outbox:process, tickets:escalate, and the scheduler configuration.
 * State: bounded batch sizes and delivery log entries; exact locations are in docs/code-index.md.
 */

use App\Models\OutboxMessage;
use App\Services\OutboxProcessor;
use App\Services\SlaEscalationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('outbox:process {--limit=25}', function (OutboxProcessor $processor): void {
    $count = $processor->process(
        function (OutboxMessage $message): void {
            Log::info('Delivered service-desk event.', [
                'topic' => $message->topic,
                'deduplication_key' => $message->deduplication_key,
            ]);
        },
        (int) $this->option('limit'),
    );
    $this->info("Processed {$count} outbox messages.");
})->purpose('Deliver a bounded batch of reliable notification messages.');

Artisan::command('tickets:escalate {--limit=100}', function (SlaEscalationService $service): void {
    $count = $service->escalateDue((int) $this->option('limit'));
    $this->info("Escalated {$count} overdue tickets.");
})->purpose('Mark and notify on a bounded batch of overdue tickets.');

Schedule::command('tickets:escalate')->everyMinute()->withoutOverlapping();
Schedule::command('outbox:process')->everyMinute()->withoutOverlapping();
