/**
 * File: Owns an isolated PHP/SQLite server for actual browser forms without reading user environment files.
 * Symbols: checkedPort, runPhp, record, cleanup and main; exact lines are in docs/code-index.md.
 * State: generated key/nonce stay process-private; only synthetic IDs are written to ignored build evidence.
 */
import { execFile, spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync } from 'node:fs';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { get, createServer as createProbe } from 'node:http';
import { tmpdir } from 'node:os';
import { dirname, join, resolve, sep } from 'node:path';
import process from 'node:process';
import { setTimeout as sleep } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';
import { stripVTControlCharacters } from 'node:util';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const php = process.env.SSD_BROWSER_PHP ?? 'php';
const port = checkedPort(process.env.SSD_BROWSER_PORT);
let child;
let temporary;
let environment;
let nonce;
let logs = '';
let bound = false;
let closing = false;

/** Reject shell-like/invalid ports before constructing any process or URL. */
function checkedPort(raw) {
    const value = raw ?? '18971';
    const port = Number(value);
    if (!/^\d{4,5}$/.test(value) || !Number.isSafeInteger(port) || port < 1024 || port > 65535)
        throw new Error('SSD_BROWSER_PORT must be 1024..65535');
    return port;
}

/** Execute real fixture CLI commands with no user environment file or database connection. */
function runPhp(arguments_) {
    return new Promise((done, reject) => {
        execFile(
            php,
            ['-d', 'variables_order=EGPCS', ...arguments_],
            { cwd: root, env: environment, timeout: 60000, maxBuffer: 20000, windowsHide: true },
            (error, stdout, stderr) =>
                error
                    ? reject(
                          new Error(
                              `Owned PHP fixture command failed: ${(stdout + stderr).slice(-16000)}`,
                          ),
                      )
                    : done(stdout),
        );
    });
}

/** Bound public startup diagnostics; recognize only the process we launched reporting its own bind. */
function record(chunk) {
    logs = (logs + stripVTControlCharacters(chunk.toString())).slice(-16000);
    if (logs.includes(`Development Server (http://127.0.0.1:${port}) started`)) bound = true;
}

/** Remove only this launcher's verified temporary directory after terminating only its child. */
async function cleanup(code) {
    if (closing) return;
    closing = true;
    if (child && child.exitCode === null && child.signalCode === null) {
        child.kill('SIGTERM');
        for (
            let attempt = 0;
            attempt < 20 && child.exitCode === null && child.signalCode === null;
            attempt++
        )
            await sleep(100);
        if (child.exitCode === null && child.signalCode === null) child.kill('SIGKILL');
    }
    if (temporary) {
        const target = resolve(temporary);
        const parent = resolve(tmpdir());
        if (
            target.startsWith(parent + sep) &&
            target.slice(parent.length + 1).startsWith('ssd-browser-') &&
            (await readFile(join(target, '.owned-fixture'), 'utf8').catch(() => '')) === nonce
        ) {
            await rm(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 100 });
        } else throw new Error('Refused cleanup of an unverified fixture directory');
    }
    process.exit(code);
}

/** Create a private disposable app configuration, apply real migrations/seeds, then start actual web routes. */
async function main() {
    if (
        !existsSync(join(root, 'vendor/autoload.php')) ||
        !existsSync(join(root, 'public/build/manifest.json'))
    )
        throw new Error('Install Composer dependencies and build real assets before browser tests');
    const probe = createProbe();
    await new Promise((ready, reject) => {
        probe.once('error', reject);
        probe.listen(port, '127.0.0.1', ready);
    });
    await new Promise((done) => probe.close(done));
    temporary = await mkdtemp(join(tmpdir(), 'ssd-browser-'));
    nonce = randomBytes(24).toString('hex');
    await writeFile(join(temporary, '.owned-fixture'), nonce, { flag: 'wx', mode: 0o600 });
    await writeFile(join(temporary, '.env.browser'), '', { flag: 'wx', mode: 0o600 });
    await writeFile(join(temporary, 'database.sqlite'), '', { flag: 'wx', mode: 0o600 });
    for (const path of [
        'bootstrap-cache',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'storage/app/private',
    ])
        mkdirSync(join(temporary, path), { recursive: true, mode: 0o700 });
    const safeBase = new Set([
        'PATH',
        'SYSTEMROOT',
        'WINDIR',
        'TEMP',
        'TMP',
        'USERPROFILE',
        'HOME',
        'COMSPEC',
    ]);
    environment = Object.fromEntries(
        Object.entries(process.env).filter(([key]) => safeBase.has(key.toUpperCase())),
    );
    const fixture = temporary.replaceAll('\\', '/');
    Object.assign(environment, {
        SSD_BROWSER_ROOT: fixture,
        SSD_BROWSER_NONCE: nonce,
        APP_ENV: 'browser-fixture',
        APP_DEBUG: 'false',
        APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
        APP_URL: `http://127.0.0.1:${port}`,
        APP_CONFIG_CACHE: `${fixture}/bootstrap-cache/config.php`,
        APP_ROUTES_CACHE: `${fixture}/bootstrap-cache/routes.php`,
        APP_EVENTS_CACHE: `${fixture}/bootstrap-cache/events.php`,
        APP_PACKAGES_CACHE: `${fixture}/bootstrap-cache/packages.php`,
        APP_SERVICES_CACHE: `${fixture}/bootstrap-cache/services.php`,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: `${fixture}/database.sqlite`,
        DB_URL: '',
        DATABASE_URL: '',
        DB_FOREIGN_KEYS: 'true',
        SESSION_DRIVER: 'database',
        SESSION_CONNECTION: 'sqlite',
        SESSION_COOKIE: `ssd_fixture_${nonce.slice(0, 8)}`,
        SESSION_DOMAIN: 'null',
        SESSION_SECURE_COOKIE: 'false',
        CACHE_STORE: 'database',
        DB_CACHE_CONNECTION: 'sqlite',
        QUEUE_CONNECTION: 'database',
        DB_QUEUE_CONNECTION: 'sqlite',
        VIEW_COMPILED_PATH: `${fixture}/storage/framework/views`,
        LOG_CHANNEL: 'single',
        LOG_LEVEL: 'warning',
        MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null',
        BCRYPT_ROUNDS: '4',
        WEBHOOK_SECRET: randomBytes(32).toString('hex'),
    });
    await runPhp([
        'tests/browser/console.php',
        'migrate:fresh',
        '--seed',
        '--force',
        '--no-interaction',
    ]);
    const seedOutput = await runPhp(['tests/browser/seed.php']);
    let manifest;
    try {
        manifest = JSON.parse(seedOutput);
    } catch {
        throw new Error(
            `Owned fixture seed did not emit valid JSON: ${stripVTControlCharacters(seedOutput).slice(-12000)}`,
        );
    }
    mkdirSync(join(root, 'build'), { recursive: true });
    await writeFile(
        join(root, 'build/browser-fixture.json'),
        JSON.stringify(manifest, null, 2) + '\n',
        { mode: 0o600 },
    );
    child = spawn(
        php,
        [
            '-d',
            'variables_order=EGPCS',
            '-S',
            `127.0.0.1:${port}`,
            '-t',
            'public',
            'tests/browser/router.php',
        ],
        { cwd: root, env: environment, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true },
    );
    child.stdout.on('data', record);
    child.stderr.on('data', record);
    child.once('error', (error) => {
        console.error(`Owned PHP process error: ${error.message}`);
        void cleanup(1);
    });
    child.once('exit', () => {
        if (!closing) {
            console.error(`Owned PHP process exited.\n${logs}`);
            void cleanup(1);
        }
    });
    for (let attempt = 0; attempt < 240; attempt++) {
        if (child.exitCode !== null || child.signalCode !== null)
            throw new Error('Owned PHP process exited during readiness');
        if (bound) {
            const ready = await new Promise((done) => {
                const probe = get(
                    { hostname: '127.0.0.1', port, path: '/up', timeout: 2000 },
                    (response) => {
                        response.resume();
                        response.once('end', () => done(response.statusCode === 200));
                    },
                );
                probe.on('error', () => done(false));
                probe.on('timeout', () => probe.destroy());
            });
            if (ready) {
                console.log(`Owned PHP/SQLite browser fixture ready on 127.0.0.1:${port}`);
                return;
            }
        }
        await sleep(250);
    }
    throw new Error('Owned PHP fixture readiness timed out');
}
process.on('SIGTERM', () => {
    void cleanup(0);
});
process.on('SIGINT', () => {
    void cleanup(0);
});
void main().catch((error) => {
    console.error(`${error.message}\n${logs}`);
    void cleanup(1);
});
