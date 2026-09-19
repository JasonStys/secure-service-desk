{{--
  File: Server-rendered service-desk dashboard with progressive enhancement.
  Symbols: ticket creation, filtering, commenting, transition, and pagination forms.
  State: actor, tickets, filters, priorities, statuses, validation errors, and flash message.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Auditable, tenant-safe service desk demonstration">
    <title>Signal Desk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <div>
        <p class="eyebrow">OPERATIONS WORKSPACE</p>
        <h1>Signal Desk</h1>
        <p class="subtitle">Tenant-safe ticket handling with explicit workflows and durable delivery.</p>
    </div>
    <nav aria-label="Demonstration identities" class="identity-switcher">
        <span>Viewing as <strong>{{ $actor->name }}</strong></span>
        <a href="{{ route('dashboard', ['actor' => 'agent@northwind.example']) }}">Agent</a>
        <a href="{{ route('dashboard', ['actor' => 'requester@northwind.example']) }}">Requester</a>
    </nav>
</header>

<main id="main" class="layout">
    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <section class="errors" role="alert" aria-labelledby="validation-title">
            <h2 id="validation-title">Please correct the following</h2>
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </section>
    @endif

    <section class="panel create-panel" aria-labelledby="create-title">
        <div class="section-heading">
            <p class="eyebrow">NEW REQUEST</p>
            <h2 id="create-title">Create a ticket</h2>
        </div>
        <form method="post" action="{{ route('tickets.store', ['actor' => $actor->email]) }}" class="ticket-form">
            @csrf
            <label>Title<input name="title" maxlength="160" required value="{{ old('title') }}"></label>
            <label>Priority
                <select name="priority" required>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ ucfirst($priority->value) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="wide">Description<textarea name="description" maxlength="4000" required rows="4">{{ old('description') }}</textarea></label>
            <label class="wide">Tags <span class="hint">comma-free slugs, up to five</span><input name="tags[]" pattern="[a-z0-9-]+" placeholder="operations"></label>
            <input type="hidden" name="request_key" value="browser-{{ Illuminate\Support\Str::uuid() }}">
            <button type="submit">Create ticket</button>
        </form>
    </section>

    <section class="panel queue-panel" aria-labelledby="queue-title">
        <div class="section-heading queue-heading">
            <div><p class="eyebrow">LIVE QUEUE</p><h2 id="queue-title">Visible tickets</h2></div>
            <span class="count">{{ $tickets->total() }} total</span>
        </div>
        <form method="get" action="{{ route('dashboard') }}" class="filters" data-filter-form>
            <input type="hidden" name="actor" value="{{ $actor->email }}">
            <label>Search<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" data-live-search></label>
            <label>Status<select name="status"><option value="">All</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
            <label>Priority<select name="priority"><option value="">All</option>@foreach ($priorities as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ ucfirst($priority->value) }}</option>@endforeach</select></label>
            <button type="submit">Apply</button>
        </form>

        <div class="ticket-list" data-ticket-list>
            @forelse ($tickets as $ticket)
                <article class="ticket" data-ticket-card data-search="{{ $ticket->public_id }} {{ $ticket->title }} {{ $ticket->description }} {{ implode(' ', $ticket->tags) }}">
                    <header>
                        <div><p class="ticket-id">{{ Str::limit($ticket->public_id, 13, '') }}</p><h3>{{ $ticket->title }}</h3></div>
                        <div class="badges"><span class="badge priority-{{ $ticket->priority->value }}">{{ $ticket->priority->value }}</span><span class="badge">{{ $ticket->status->value }}</span></div>
                    </header>
                    <p>{{ $ticket->description }}</p>
                    <dl class="metadata">
                        <div><dt>Requester</dt><dd>{{ $ticket->requester->name }}</dd></div>
                        <div><dt>SLA due</dt><dd>{{ $ticket->sla_due_at->format('M j, Y H:i T') }}</dd></div>
                        <div><dt>Version</dt><dd>{{ $ticket->version }}</dd></div>
                    </dl>
                    @if ($ticket->tags)<p class="tags" aria-label="Tags">@foreach ($ticket->tags as $tag)<span>#{{ $tag }}</span>@endforeach</p>@endif
                    <div class="ticket-actions">
                        <form method="post" action="{{ route('tickets.comments.store', ['ticket' => $ticket, 'actor' => $actor->email]) }}">
                            @csrf
                            <label>Comment<input name="body" maxlength="2000" required></label>
                            <button type="submit" class="secondary">Add</button>
                        </form>
                        @if ($actor->role->canManageTickets() && ! $ticket->status->terminal())
                            <form method="post" action="{{ route('tickets.transition', ['ticket' => $ticket, 'actor' => $actor->email]) }}" data-transition-form>
                                @csrf
                                <input type="hidden" name="version" value="{{ $ticket->version }}">
                                <label>Move to<select name="status">@foreach ($statuses as $status)@if ($ticket->status->canTransitionTo($status))<option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>@endif @endforeach</select></label>
                                <button type="submit" class="secondary">Update</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="empty">No tickets match these filters.</p>
            @endforelse
        </div>

        @if ($tickets->hasPages())
            <nav class="pagination" aria-label="Ticket pages">
                @if ($tickets->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $tickets->previousPageUrl() }}">Previous</a>@endif
                <span>Page {{ $tickets->currentPage() }} of {{ $tickets->lastPage() }}</span>
                @if ($tickets->hasMorePages())<a href="{{ $tickets->nextPageUrl() }}">Next</a>@else<span aria-disabled="true">Next</span>@endif
            </nav>
        @endif
    </section>
</main>
<footer><p>All names and organizations shown are synthetic demonstration data.</p></footer>
</body>
</html>
