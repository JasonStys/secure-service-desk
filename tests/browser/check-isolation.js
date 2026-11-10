/**
 * File: Verifies browser fixture guards before any user service or database can be adopted.
 * Symbols: run and main; owned listener/child handles are created and closed within this command.
 * State: only public synthetic response nonce; no real .env is read or modified.
 */
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { createServer, get } from 'node:http';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

/** Execute only a known local fixture entry point with bounded timeout/output. */
function run(executable, args, environment) {
    return new Promise((done) =>
        execFile(
            executable,
            args,
            {
                cwd: root,
                env: { ...process.env, ...environment },
                timeout: 10000,
                maxBuffer: 24000,
                windowsHide: true,
            },
            (error, stdout, stderr) => done({ error, output: stdout + stderr }),
        ),
    );
}
/** Refuse malformed/occupied ports, preserve the existing listener and require private bootstrap proof. */
async function main() {
    const invalid = await run(process.execPath, ['tests/browser/server.js'], {
        SSD_BROWSER_PORT: 'bad-port',
    });
    assert(invalid.error);
    assert.match(invalid.output, /SSD_BROWSER_PORT must be 1024\.\.65535/);
    const bootstrap = await run(
        process.env.SSD_BROWSER_PHP ?? 'php',
        ['tests/browser/console.php', 'migrate:fresh', '--force'],
        { SSD_BROWSER_ROOT: '', SSD_BROWSER_NONCE: '', APP_ENV: 'browser-fixture' },
    );
    assert(bootstrap.error);
    assert.match(bootstrap.output, /Browser bootstrap requires its owned temporary fixture/);
    const listener = createServer((_request, response) => response.end('owned-service-preserved'));
    await new Promise((done) => listener.listen(0, '127.0.0.1', done));
    try {
        const address = listener.address();
        assert(address && typeof address === 'object');
        const occupied = await run(process.execPath, ['tests/browser/server.js'], {
            SSD_BROWSER_PORT: String(address.port),
        });
        assert(occupied.error);
        assert.match(occupied.output, /EADDRINUSE/);
        assert.doesNotMatch(occupied.output, /Owned PHP\/SQLite browser fixture ready/);
        const body = await new Promise((done, reject) => {
            const request = get(
                { hostname: '127.0.0.1', port: address.port, path: '/', timeout: 2000 },
                (response) => {
                    let value = '';
                    response.on('data', (chunk) => {
                        value += chunk.toString();
                    });
                    response.on('end', () => done(value));
                },
            );
            request.on('error', reject);
            request.on('timeout', () => request.destroy(new Error('fixture listener timed out')));
        });
        assert.equal(body, 'owned-service-preserved');
    } finally {
        await new Promise((done) => listener.close(done));
    }
    console.log(
        'Browser isolation guards: invalid port, missing private bootstrap, occupied-port refusal and listener preservation passed.',
    );
}
await main();
