<?php

/**
 * File: Routes actual HTTP/browser requests through Laravel and serves only real built public assets.
 * Symbols: bounded public-file routing and isolated request entry point.
 * State: test server URI, canonical public root and request; docs/code-index.md lists lines.
 */

use Illuminate\Http\Request;

$public = realpath(dirname(__DIR__, 2).'/public');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = is_string($uri) ? realpath($public.'/'.rawurldecode($uri)) : false;
if ($file !== false && is_file($file) && str_starts_with($file, $public.DIRECTORY_SEPARATOR)
    && ! str_ends_with($file, '.php')) {
    return false;
}
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Request::capture());
