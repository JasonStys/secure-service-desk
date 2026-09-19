<?php

/**
 * File: Resolves a seeded demonstration actor without weakening domain authorization.
 * Symbols: ResolveDemoActor middleware and handle().
 * State: X-Demo-Actor email input and request actor attribute; exact locations are in docs/code-index.md.
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveDemoActor
{
    /** Resolve an allowed demo identity and attach it to the request. */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->header('X-Demo-Actor') ?? $request->query('actor');

        if ($email === null && app()->environment(['local', 'testing'])) {
            $email = 'agent@northwind.example';
        }

        $actor = is_string($email) ? User::query()->where('email', $email)->first() : null;
        abort_if($actor === null, 401, 'A valid demonstration actor is required.');

        auth()->setUser($actor);
        $request->attributes->set('actor', $actor);

        return $next($request);
    }
}
