<?php

/**
 * File: native/bootstrap.php
 * Purpose: Boot the real Laravel application with only its owned durable native profile.
 * Functions: checked profile/key, configuration assertions and local caches; see docs/code-index.md.
 * State: owned durable profile path, generated key and fixed local origin only.
 * No developer .env, cached installation config, shared password or external database is loaded.
 */

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Vite;

$profile = getenv('SSD_NATIVE_ROOT');
$key = getenv('APP_KEY');
$origin = getenv('APP_URL');
if (! is_string($profile) || ! is_string($key) || ! is_string($origin)
    || getenv('APP_ENV') !== 'native-local' || ! preg_match('/^http:\/\/127\.0\.0\.1:[0-9]{1,5}$/D', $origin)
    || file_exists($profile.'/.env.native-disabled')) {
    throw new RuntimeException('The native workspace requires its owned local profile.');
}
$raw = file_get_contents($profile.'/profile.json', false, null, 0, 4097);
$identity = is_string($raw) && strlen($raw) <= 4096 ? json_decode($raw, true, 8, JSON_THROW_ON_ERROR) : null;
if (! is_array($identity) || array_keys($identity) !== ['schema', 'product', 'key']
    || $identity['schema'] !== 1 || $identity['product'] !== 'secure-service-desk'
    || ! is_string($identity['key']) || ! hash_equals($identity['key'], $key)) {
    throw new RuntimeException('The native workspace identity does not match.');
}

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->useEnvironmentPath($profile)->loadEnvironmentFrom('.env.native-disabled');
$app->useStoragePath($profile.'/storage');
$app->addAbsoluteCachePathPrefix($profile.'/');
$app->afterResolving(Vite::class, function (Vite $vite) use ($profile): void {
    $vite->useHotFile($profile.'/no-hmr');
});
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($profile, $origin): void {
    $expected = str_replace('\\', '/', $profile).'/database.sqlite';
    $actual = str_replace('\\', '/', (string) $app['config']->get('database.connections.sqlite.database'));
    if ($app['config']->get('database.default') !== 'sqlite' || $actual !== $expected
        || $app['config']->get('app.env') !== 'native-local'
        || $app['config']->get('app.debug') !== false || $app['config']->get('app.url') !== $origin
        || $app['config']->get('session.driver') !== 'database' || $app->configurationIsCached()
        || $app['config']->get('mail.default') !== 'array') {
        throw new RuntimeException('Native configuration refused non-local storage or unsafe defaults.');
    }
});

return $app;
