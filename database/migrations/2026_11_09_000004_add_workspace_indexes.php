<?php

/**
 * File: Add non-destructive queue ownership and bounded ticket-history indexes.
 * Symbols: anonymous migration up/down; Jason Stys.
 * State: indexes only, no ticket content, ownership or history is rewritten.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Support filtered owner queues and per-ticket reverse-ID discussion/audit pages. */
    public function up(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->index(['tenant_id', 'assignee_id', 'created_at', 'id'], 'tickets_owner_queue_idx'));
        Schema::table('tickets', fn (Blueprint $table) => $table->index(['sla_breached_at', 'sla_due_at', 'id'], 'tickets_sla_scan_idx'));
        Schema::table('ticket_comments', fn (Blueprint $table) => $table->index(['ticket_id', 'id'], 'comments_ticket_id_idx'));
        Schema::table('audit_entries', fn (Blueprint $table) => $table->index(['ticket_id', 'id'], 'audit_ticket_id_idx'));
    }

    /** Remove only indexes introduced here, preserving all stored data. */
    public function down(): void
    {
        Schema::table('audit_entries', fn (Blueprint $table) => $table->dropIndex('audit_ticket_id_idx'));
        Schema::table('ticket_comments', fn (Blueprint $table) => $table->dropIndex('comments_ticket_id_idx'));
        Schema::table('tickets', fn (Blueprint $table) => $table->dropIndex('tickets_owner_queue_idx'));
        Schema::table('tickets', fn (Blueprint $table) => $table->dropIndex('tickets_sla_scan_idx'));
    }
};
