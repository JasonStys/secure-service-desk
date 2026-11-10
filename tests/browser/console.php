<?php

/**
 * File: Runs real migrations and seed commands through the isolated browser-fixture bootstrap.
 * Symbols: command entry point; no application routes or environment files are changed.
 * State: Symfony argument vector and owned fixture application; docs/code-index.md lists lines.
 */

use Symfony\Component\Console\Input\ArgvInput;

$app = require __DIR__.'/bootstrap.php';
exit($app->handleCommand(new ArgvInput));
