{{--
 File: Tenant-scoped ticket workspace with bounded discussion and audit pages.
 Symbols: ownership, lifecycle, comment and history forms; Jason Stys.
 State: authorized actor/ticket, eligible agents, independent bounded history windows.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket workspace · Signal Desk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header"><div><p class="eyebrow">TICKET WORKSPACE</p><h1>{{ $ticket->title }}</h1><p class="ticket-id">{{ $ticket->public_id }}</p></div>
    <nav aria-label="Workspace navigation"><a href="{{ route('dashboard', ['actor' => $actor->email]) }}">Back to queue</a><p>Viewing as {{ $actor->name }}</p></nav>
</header>
<main id="main" class="detail-layout">
    @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<section class="errors" role="alert"><h2>Please correct the following</h2><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Refresh the workspace if the version changed. Your attempted change was not applied.</p></section>@endif
    <section class="panel" aria-labelledby="state-title"><h2 id="state-title">Current request</h2>
        <p class="prewrap">{{ $ticket->description }}</p>
        <dl class="metadata"><div><dt>Status</dt><dd>{{ $ticket->status->value }}</dd></div><div><dt>Priority</dt><dd>{{ $ticket->priority->value }}</dd></div><div><dt>Owner</dt><dd>{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div><div><dt>Requester</dt><dd>{{ $ticket->requester->name }}</dd></div><div><dt>Version</dt><dd>{{ $ticket->version }}</dd></div><div><dt>SLA due</dt><dd><time datetime="{{ $ticket->sla_due_at->toIso8601String() }}">{{ $ticket->sla_due_at->format('Y-m-d H:i:s T') }}</time></dd></div></dl>
        <p>State and version describe this page load; history pages are separate read requests, not a signed snapshot. Refresh before acting on changes made by another agent.</p>
        @if ($actor->role->canManageTickets())
            @if (!in_array($ticket->status->value, ['resolved', 'closed'], true))
                <form method="post" action="{{ route('tickets.assign', ['ticket' => $ticket, 'actor' => $actor->email]) }}" class="workspace-form" aria-label="Ticket ownership">
                    @csrf<input type="hidden" name="version" value="{{ $ticket->version }}">
                    <label>Assign to<select name="assignee_id"><option value="" @selected($ticket->assignee_id === null)>Unassigned</option>@if ($ticket->assignee !== null && !$agents->take(200)->contains('id', $ticket->assignee_id))<option value="{{ $ticket->assignee_id }}" selected>{{ $ticket->assignee->name }} (current owner; eligibility rechecked on save)</option>@endif @foreach ($agents->take(200) as $agent)<option value="{{ $agent->id }}" @selected($ticket->assignee_id === $agent->id)>{{ $agent->name }}</option>@endforeach</select></label><button type="submit">Save assignment</button>
                </form>
                @if ($agents->count() > 200)<p>Showing the first 200 eligible agents by name. Other eligible IDs can be assigned through the validated API.</p>@endif
            @else<p>Assignment is disabled for resolved/closed work. Reopen resolved work before changing ownership.</p>@endif
            @if (!$ticket->status->terminal())<form method="post" action="{{ route('tickets.transition', ['ticket' => $ticket, 'actor' => $actor->email]) }}" class="workspace-form" data-transition-form aria-label="Ticket lifecycle">
                @csrf<input type="hidden" name="version" value="{{ $ticket->version }}"><label>Move to<select name="status">@foreach ($statuses as $status)@if ($ticket->status->canTransitionTo($status))<option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>@endif @endforeach</select></label><button type="submit">Update status</button></form>@endif
        @else<p>You can view and comment on your own request. Service agents manage ownership and lifecycle.</p>@endif
    </section>
    <section class="panel" aria-labelledby="discussion-title"><h2 id="discussion-title">Discussion</h2>
        <form method="post" action="{{ route('tickets.comments.store', ['ticket' => $ticket, 'actor' => $actor->email]) }}" class="workspace-form">@csrf<label>Comment<textarea name="body" maxlength="2000" required rows="3">{{ old('body') }}</textarea></label><button type="submit">Add comment</button></form>
        <p>Newest first, up to 20 comments per page. Comments are plain text.</p>
        <ol>@forelse ($comments as $comment)<li><strong>{{ $comment->author->name }}</strong> · <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->toIso8601String() }}</time><p class="prewrap">{{ $comment->body }}</p></li>@empty<li>No comments yet.</li>@endforelse</ol>
        <nav class="pagination" aria-label="Discussion pages">@if($comments->previousPageUrl())<a href="{{ $comments->previousPageUrl() }}">Newer comments</a>@else<span>Newest page</span>@endif<span>Page {{ $comments->currentPage() }}</span>@if($comments->nextPageUrl())<a href="{{ $comments->nextPageUrl() }}">Older comments</a>@else<span>No older comments</span>@endif</nav>
    </section>
    <section class="panel" aria-labelledby="history-title"><h2 id="history-title">Audit history</h2><p>Newest first, up to 20 events per page. Events record committed changes; a repeated identical assignment creates no extra event.</p>
        <ol>@forelse($audit as $entry)<li><strong>{{ $entry->event }}</strong> · <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->toIso8601String() }}</time><pre>{{ json_encode($entry->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></li>@empty<li>No recorded events.</li>@endforelse</ol>
        <nav class="pagination" aria-label="Audit pages">@if($audit->previousPageUrl())<a href="{{ $audit->previousPageUrl() }}">Newer audit events</a>@else<span>Newest page</span>@endif<span>Page {{ $audit->currentPage() }}</span>@if($audit->nextPageUrl())<a href="{{ $audit->nextPageUrl() }}">Older audit events</a>@else<span>No older audit events</span>@endif</nav>
    </section>
    <details class="panel"><summary>Help and workflow limits</summary><p>Choose an eligible tenant agent or Unassigned, save, then use the refreshed version for the next change. A stale version is rejected rather than overwriting someone else's work. Assignment preserves SLA dates and state. This demo identity selector is not production authentication; use only synthetic data. Search/owner/SLA filters narrow your existing access and never grant access to another tenant or requester.</p></details>
</main>
<footer><p>Created by <a href="https://github.com/JasonStys">Jason Stys</a>. Synthetic local service-desk demonstration.</p></footer>
</body>
</html>
