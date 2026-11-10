<?php

/**
 * File: Creates an isolated browser-fixture application without loading the user's .env or caches.
 * Symbols: guarded application bootstrap and configuration assertion callback.
 * State: only test-owned temp SQLite/storage/environment paths; docs/code-index.md lists exact lines.
 */

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Vite;

$fixture = getenv('SSD_BROWSER_ROOT');
$nonce = getenv('SSD_BROWSER_NONCE');
if (! is_string($fixture) || ! is_string($nonce) || ! str_starts_with(basename($fixture), 'ssd-browser-')
    || ! is_file($fixture.'/.owned-fixture') || ! hash_equals($nonce, file_get_contents($fixture.'/.owned-fixture'))
    || getenv('APP_ENV') !== 'browser-fixture') {
    throw new RuntimeException('Browser bootstrap requires its owned temporary fixture.');
}

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($fixture)->loadEnvironmentFrom('.env.browser');
$app->useStoragePath($fixture.'/storage');
$app->addAbsoluteCachePathPrefix($fixture.'/');
$app->afterResolving(Vite::class, function (Vite $vite) use ($fixture): void {
    $vite->useHotFile($fixture.'/no-hmr');
});
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($fixture): void {
    $expected = str_replace('\\', '/', $fixture).'/database.sqlite';
    $actual = str_replace('\\', '/', (string) $app['config']->get('database.connections.sqlite.database'));
    if ($app['config']->get('database.default') !== 'sqlite' || $actual !== $expected
        || $app['config']->get('app.env') !== 'browser-fixture'
        || $app['config']->get('session.driver') !== 'database'
        || $app->configurationIsCached()) {
        throw new RuntimeException('Browser fixture refused non-isolated application configuration.');
    }
});

return $app;
