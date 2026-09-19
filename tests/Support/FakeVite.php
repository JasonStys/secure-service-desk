<?php

/**
 * File: Supplies a named, coverage-safe Vite test double without requiring built assets.
 * Symbols: FakeVite and __invoke().
 * State: no mutable state; exact declaration is indexed in docs/code-index.md.
 */

namespace Tests\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

class FakeVite extends Vite
{
    /** Return an empty asset fragment while exercising server-rendered responses. */
    public function __invoke($entrypoints, $buildDirectory = null): HtmlString
    {
        return new HtmlString('');
    }
}
