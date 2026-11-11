<?php

/**
 * File: native/public.php
 * Purpose: Handle real Blade forms/API/CSRF through fixed CGI entry code; private port belongs to Node.
 * Symbols: isolated Request capture and actual Laravel handler entry point.
 * State: owned environment and captured HTTP request, never a caller-selected PHP script path.
 */

use Illuminate\Http\Request;

$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Request::capture());
