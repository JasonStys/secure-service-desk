<?php

/**
 * File: Creates the tenant-isolated ticket, audit, outbox, and webhook schema.
 * Symbols: anonymous migration with up() and down().
 * State: tenants, tickets, comments, audit entries, outbox messages, and webhook receipts.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create all service-desk tables and database-specific search indexes. */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('requester')->after('password');
            $table->index(['tenant_id', 'role']);
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('request_key', 100)->nullable();
            $table->string('title', 160);
            $table->text('description');
            $table->string('status', 20)->default('new');
            $table->string('priority', 20)->default('normal');
            $table->json('tags')->default('[]');
            $table->timestampTz('sla_due_at');
            $table->timestampTz('sla_breached_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'request_key']);
            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['tenant_id', 'sla_due_at']);
            $table->index(['tenant_id', 'requester_id', 'created_at']);
        });

        Schema::create('ticket_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampsTz();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->json('metadata');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('deduplication_key', 160)->unique();
            $table->string('topic', 80);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->string('last_error', 500)->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'available_at']);
        });

        Schema::create('webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('provider', 80);
            $table->string('external_event_id', 160);
            $table->char('payload_hash', 64);
            $table->json('response');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'provider', 'external_event_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX tickets_search_idx ON tickets USING GIN (to_tsvector('english', title || ' ' || description))");
        }
    }

    /** Remove service-desk tables in reverse dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('audit_entries');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('tenants');
    }
};
