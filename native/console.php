<?php

/**
 * File: native/console.php
 * Purpose: Run real migrations/seeding through the isolated native bootstrap, never migrate:fresh.
 * Symbols: the fixed native CLI command entry point.
 * State: fixed command arguments supplied only by the native launcher; exact lines in docs/code-index.md.
 */

use Symfony\Component\Console\Input\ArgvInput;

$app = require __DIR__.'/bootstrap.php';
exit($app->handleCommand(new ArgvInput));
