/**
 * File: desktop/runtime.mjs
 * Purpose: Own a durable local profile and one port-zero HTTP boundary around the real PHP/Laravel app.
 * Functions: see docs/code-index.md. The fixed CGI entry receives raw bounded requests, never
 * commands or selected script paths. No developer .env or outside service settings are inherited.
 * State: owned local profile/key, one ephemeral listener, bounded child requests and shutdown promise.
 */
import { spawn } from 'node:child_process';
import { randomBytes, timingSafeEqual } from 'node:crypto';
import { constants } from 'node:fs';
import { mkdir, open, readdir, lstat } from 'node:fs/promises';
import { createServer } from 'node:http';
import { join, resolve, dirname, basename, sep } from 'node:path';
import process from 'node:process';

export const VERSION = '1.1.0';
const MAX_BODY = 128 * 1024;
const MAX_RESPONSE = 8 * 1024 * 1024;

/** Bind metadata to an actual regular one-link descriptor, refuse swapped paths and oversized reads. */
export async function readBounded(path, limit) {
    const file = await open(
        path,
        constants.O_RDONLY | (constants.O_NOFOLLOW ?? 0) | (constants.O_NONBLOCK ?? 0),
    );
    try {
        const info = await file.stat();
        const named = await lstat(path);
        if (
            !info.isFile() ||
            info.nlink !== 1 ||
            info.size > limit ||
            named.isSymbolicLink() ||
            named.dev !== info.dev ||
            named.ino !== info.ino
        )
            throw new Error('Unsafe local file.');
        const bytes = Buffer.alloc(Math.min(info.size + 1, limit + 1));
        let count = 0;
        while (count < bytes.length) {
            const part = await file.read(bytes, count, bytes.length - count, count);
            if (part.bytesRead === 0) break;
            count += part.bytesRead;
        }
        if (count !== info.size || count > limit)
            throw new Error('Local file changed or exceeded its limit.');
        return bytes.subarray(0, count);
    } finally {
        await file.close();
    }
}

/** Publish only new profile metadata; never reset unknown contents, credentials or an existing database. */
export async function profileIdentity(profile) {
    const absolute = resolve(profile);
    for (let path = absolute; ; path = dirname(path)) {
        const info = await lstat(path).catch((error) =>
            error.code === 'ENOENT' ? null : Promise.reject(error),
        );
        if (info?.isSymbolicLink()) throw new Error('Linked native profile path refused.');
        if (dirname(path) === path) break;
    }
    await mkdir(absolute, { recursive: true, mode: 0o700 });
    const marker = join(absolute, 'profile.json');
    let fresh = false;
    const entries = await readdir(absolute);
    if (entries.length === 0) {
        const identity = {
            schema: 1,
            product: 'secure-service-desk',
            key: `base64:${randomBytes(32).toString('base64')}`,
        };
        const file = await open(marker, 'wx', 0o600);
        try {
            await file.writeFile(JSON.stringify(identity) + '\n');
            await file.sync();
        } finally {
            await file.close();
        }
        fresh = true;
    } else if (!entries.includes('profile.json')) {
        throw new Error('Unknown nonempty profile; no files were reset.');
    }
    const serialized = (await readBounded(marker, 4096)).toString('utf8');
    const identity = JSON.parse(serialized);
    if (
        JSON.stringify(Object.keys(identity)) !== JSON.stringify(['schema', 'product', 'key']) ||
        serialized !== JSON.stringify(identity) + '\n' ||
        identity.schema !== 1 ||
        identity.product !== 'secure-service-desk' ||
        typeof identity.key !== 'string' ||
        !/^base64:[A-Za-z0-9+/]{43}=$/.test(identity.key)
    ) {
        throw new Error('Native profile identity is invalid; no files were reset.');
    }
    const database = join(absolute, 'database.sqlite');
    if (fresh) {
        const file = await open(database, 'wx', 0o600);
        await file.close();
    } else {
        const file = await open(
            database,
            constants.O_RDWR | (constants.O_NOFOLLOW ?? 0) | (constants.O_NONBLOCK ?? 0),
        );
        try {
            const info = await file.stat();
            if (!info.isFile() || info.nlink !== 1 || (await lstat(database)).isSymbolicLink())
                throw new Error('Unsafe database file.');
        } finally {
            await file.close();
        }
    }
    for (const directory of [
        'bootstrap-cache',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'storage/app/private',
    ]) {
        await mkdir(join(absolute, directory), { recursive: true, mode: 0o700 });
    }
    return { absolute, identity, fresh };
}

/** Use explicit PHP arguments/ini values; no shell or inherited PHPRC, proxies, database URLs or API keys. */
export function phpArguments(phpDirectory, cgi = false) {
    const extension = join(phpDirectory, 'ext');
    return [
        '-n',
        '-d',
        `extension_dir=${extension}`,
        ...['mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'fileinfo'].flatMap((name) => [
            '-d',
            `extension=${name}`,
        ]),
        '-d',
        'variables_order=EGPCS',
        '-d',
        'display_errors=0',
        '-d',
        'log_errors=0',
        '-d',
        'expose_php=0',
        '-d',
        'max_execution_time=30',
        '-d',
        'memory_limit=128M',
        '-d',
        'post_max_size=128K',
        '-d',
        'upload_max_filesize=128K',
        '-d',
        'allow_url_fopen=0',
        '-d',
        'allow_url_include=0',
        ...(cgi ? ['-d', 'cgi.force_redirect=0', '-d', 'cgi.fix_pathinfo=0'] : []),
    ];
}

/** Parse bounded CGI headers without forwarding server identities, arbitrary redirects or hop-by-hop headers. */
export function parseCgi(buffer, origin) {
    let split = buffer.indexOf('\r\n\r\n');
    let offset = 4;
    if (split < 0) {
        split = buffer.indexOf('\n\n');
        offset = 2;
    }
    if (split < 0 || split > 32768) throw new Error('Invalid CGI response headers.');
    const text = buffer.subarray(0, split).toString('utf8');
    let status = 200;
    let statusSeen = false;
    const headers = {};
    const allowed = new Set([
        'content-type',
        'content-language',
        'cache-control',
        'expires',
        'pragma',
        'location',
        'set-cookie',
        'vary',
        'x-content-type-options',
        'referrer-policy',
    ]);
    for (const line of text.split(/\r?\n/)) {
        const colon = line.indexOf(':');
        if (colon < 1 || /[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/.test(line))
            throw new Error('Invalid CGI header.');
        const key = line.slice(0, colon).toLowerCase();
        const value = line.slice(colon + 1).trim();
        if (key === 'status') {
            if (statusSeen || !/^[2-5]\d\d(?: [\x20-\x7e]*)?$/.test(value))
                throw new Error('Invalid CGI status.');
            statusSeen = true;
            status = Number(value.slice(0, 3));
        } else if (allowed.has(key)) {
            if (key === 'location' && new URL(value, origin).origin !== origin)
                throw new Error('Outside redirect refused.');
            if (key === 'set-cookie') (headers[key] ??= []).push(value);
            else if (Object.hasOwn(headers, key)) throw new Error('Ambiguous CGI header.');
            else headers[key] = value;
        }
    }
    return { status, headers, body: buffer.subarray(split + offset) };
}

/** Run one fixed child, retain it through client disconnect, bound output/time and await actual exit. */
function execute(executable, args, options, body = Buffer.alloc(0)) {
    return new Promise((done, reject) => {
        const child = spawn(executable, args, {
            ...options,
            windowsHide: true,
            shell: false,
            stdio: ['pipe', 'pipe', 'pipe'],
        });
        let size = 0;
        let failed = false;
        const chunks = [];
        const timer = setTimeout(() => {
            failed = true;
            child.kill();
        }, 60_000);
        child.stdout.on('data', (chunk) => {
            size += chunk.length;
            if (size > MAX_RESPONSE) {
                failed = true;
                child.kill();
            } else chunks.push(chunk);
        });
        child.stderr.resume(); // Never retain private request/SQL contents in native diagnostics.
        child.stdin.on('error', () => {});
        child.once('error', () => {
            clearTimeout(timer);
            reject(new Error('Bundled PHP process could not start.'));
        });
        child.once('close', (code) => {
            clearTimeout(timer);
            if (failed || code !== 0)
                reject(new Error('Bundled PHP request failed or exceeded its boundary.'));
            else done(Buffer.concat(chunks, size));
        });
        child.stdin.end(body);
    });
}

/** Start one actual Laravel workspace on an owned literal-loopback ephemeral port. */
export async function createNativeService({ root, phpDirectory, profile }) {
    root = resolve(root);
    phpDirectory = resolve(phpDirectory);
    profile = resolve(profile);
    if (
        profile === root ||
        profile.startsWith(root + sep) ||
        profile === phpDirectory ||
        profile.startsWith(phpDirectory + sep)
    ) {
        throw new Error('The native profile must remain outside application/runtime files.');
    }
    const { absolute, identity, fresh } = await profileIdentity(profile);
    const initializedPath = join(absolute, 'initialization.json');
    const initializedBytes = '{"schema":1,"product":"secure-service-desk","initialized":true}\n';
    let initialized = false;
    try {
        initialized =
            (await readBounded(initializedPath, 256)).toString('utf8') === initializedBytes;
        if (!initialized)
            throw new Error('Native initialization marker is invalid; no work was reset.');
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
    }
    const token = randomBytes(32).toString('hex');
    const pending = new Set();
    let ready = false;
    let closing = false;
    let origin;
    let environment;
    let requestCount = 0;
    let windowStarted = Date.now();
    let activeRequests = 0;
    let stopped;
    const server = createServer(async (request, response) => {
        const finish = (status, message) => {
            if (response.destroyed || response.writableEnded) return;
            if (!response.headersSent)
                response.writeHead(status, {
                    'content-type': 'text/plain; charset=utf-8',
                    'cache-control': 'no-store',
                });
            response.end(message);
        };
        try {
            const supplied = request.headers['x-ssd-native'];
            if (
                request.headers.host !== new URL(origin).host ||
                typeof supplied !== 'string' ||
                !/^[a-f0-9]{64}$/.test(supplied) ||
                !timingSafeEqual(Buffer.from(supplied), Buffer.from(token))
            ) {
                finish(403, 'This local workspace belongs to its native application.');
                return;
            }
            if (request.headers.origin && request.headers.origin !== origin) {
                finish(403, 'Outside origin refused.');
                return;
            }
            if (
                !['GET', 'HEAD', 'POST'].includes(request.method) ||
                (request.method === 'POST' && request.headers.origin !== origin)
            ) {
                finish(403, 'Unsupported request origin or method.');
                return;
            }
            if (!ready || closing) {
                finish(503, 'Workspace starting or closing.');
                return;
            }
            if (Date.now() - windowStarted >= 60_000) {
                requestCount = 0;
                windowStarted = Date.now();
            }
            if (++requestCount > 180) {
                response.setHeader('retry-after', '60');
                finish(429, 'Local request limit reached; wait one minute.');
                return;
            }
            const url = new URL(request.url, origin);
            if (
                url.origin !== origin ||
                request.url.length > 8192 ||
                [...url.searchParams.keys()].some(
                    (key) => !/^[A-Za-z_][A-Za-z0-9_]*(?:\[\])?$/.test(key),
                )
            ) {
                finish(400, 'Invalid local URL.');
                return;
            }
            response.setHeader('x-content-type-options', 'nosniff');
            // Fetch serializes navigation POST Origin as null under no-referrer. Same-origin
            // preserves strict local Origin validation and Laravel back(), without outside referrers.
            response.setHeader('referrer-policy', 'same-origin');
            response.setHeader(
                'content-security-policy',
                "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            );
            if (url.pathname.startsWith('/build/assets/')) {
                if (
                    request.method === 'POST' ||
                    !/^\/build\/assets\/[A-Za-z0-9_.-]+\.(?:js|css)$/.test(url.pathname)
                ) {
                    finish(404, 'Asset unavailable.');
                    return;
                }
                const data = await readBounded(
                    join(root, 'public', 'build', 'assets', basename(url.pathname)),
                    MAX_RESPONSE,
                );
                response.setHeader(
                    'content-type',
                    url.pathname.endsWith('.css')
                        ? 'text/css; charset=utf-8'
                        : 'text/javascript; charset=utf-8',
                );
                response.writeHead(200);
                response.end(request.method === 'HEAD' ? undefined : data);
                return;
            }
            if (activeRequests >= 2) {
                response.setHeader('retry-after', '1');
                finish(503, 'Workspace busy; retry after the current operation.');
                return;
            }
            activeRequests++;
            try {
                request.setTimeout(10_000, () => request.destroy());
                const chunks = [];
                let length = 0;
                for await (const chunk of request) {
                    length += chunk.length;
                    if (length > MAX_BODY) {
                        finish(413, 'Request exceeds 128 KiB.');
                        return;
                    }
                    chunks.push(chunk);
                }
                const body = Buffer.concat(chunks, length);
                const headers = {};
                for (const key of [
                    'cookie',
                    'accept',
                    'accept-language',
                    'user-agent',
                    'x-requested-with',
                    'x-demo-actor',
                    'origin',
                    'referer',
                ]) {
                    const value = request.headers[key];
                    if (typeof value === 'string' && value.length <= 8192)
                        headers['HTTP_' + key.toUpperCase().replaceAll('-', '_')] = value;
                }
                const operation = execute(
                    join(phpDirectory, 'php-cgi.exe'),
                    phpArguments(phpDirectory, true),
                    {
                        cwd: root,
                        env: {
                            ...environment,
                            ...headers,
                            GATEWAY_INTERFACE: 'CGI/1.1',
                            SERVER_PROTOCOL: 'HTTP/1.1',
                            SERVER_SOFTWARE: 'SecureServiceDeskNative',
                            SERVER_NAME: '127.0.0.1',
                            SERVER_PORT: new URL(origin).port,
                            HTTP_HOST: new URL(origin).host,
                            REMOTE_ADDR: '127.0.0.1',
                            REQUEST_METHOD: request.method,
                            REQUEST_URI: url.pathname + url.search,
                            QUERY_STRING: url.search.slice(1),
                            REDIRECT_STATUS: '200',
                            SCRIPT_NAME: '/index.php',
                            SCRIPT_FILENAME: join(root, 'native/public.php'),
                            DOCUMENT_ROOT: join(root, 'public'),
                            CONTENT_TYPE: request.headers['content-type'] ?? '',
                            CONTENT_LENGTH: String(body.length),
                        },
                    },
                    body,
                );
                pending.add(operation);
                let result;
                try {
                    result = parseCgi(await operation, origin);
                } finally {
                    pending.delete(operation);
                }
                response.writeHead(result.status, result.headers);
                response.end(request.method === 'HEAD' ? undefined : result.body);
            } finally {
                activeRequests--;
            }
        } catch {
            finish(502, 'The local request could not complete. Existing saved work was not reset.');
        }
    });
    server.headersTimeout = 10_000;
    server.requestTimeout = 30_000;
    server.keepAliveTimeout = 1_000;
    server.maxConnections = 16;
    await new Promise((done, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', done);
    });
    origin = `http://127.0.0.1:${server.address().port}`;
    environment = Object.fromEntries(
        Object.entries(process.env).filter(([key]) =>
            ['SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP'].includes(key.toUpperCase()),
        ),
    );
    const local = absolute.replaceAll('\\', '/');
    Object.assign(environment, {
        PATH: join(process.env.SYSTEMROOT ?? 'C:/Windows', 'System32'),
        SSD_NATIVE_ROOT: local,
        APP_ENV: 'native-local',
        APP_DEBUG: 'false',
        APP_KEY: identity.key,
        APP_URL: origin,
        APP_CONFIG_CACHE: `${local}/bootstrap-cache/config.php`,
        APP_ROUTES_CACHE: `${local}/bootstrap-cache/routes.php`,
        APP_EVENTS_CACHE: `${local}/bootstrap-cache/events.php`,
        APP_PACKAGES_CACHE: `${local}/bootstrap-cache/packages.php`,
        APP_SERVICES_CACHE: `${local}/bootstrap-cache/services.php`,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: `${local}/database.sqlite`,
        DB_URL: '',
        DATABASE_URL: '',
        DB_FOREIGN_KEYS: 'true',
        SESSION_DRIVER: 'database',
        SESSION_CONNECTION: 'sqlite',
        SESSION_COOKIE: `ssd_native_${randomBytes(8).toString('hex')}`,
        SESSION_DOMAIN: 'null',
        SESSION_SECURE_COOKIE: 'false',
        CACHE_STORE: 'database',
        DB_CACHE_CONNECTION: 'sqlite',
        QUEUE_CONNECTION: 'database',
        DB_QUEUE_CONNECTION: 'sqlite',
        VIEW_COMPILED_PATH: `${local}/storage/framework/views`,
        LOG_CHANNEL: 'single',
        LOG_LEVEL: 'warning',
        MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null',
        WEBHOOK_SECRET: randomBytes(32).toString('hex'),
    });
    let runtimeVersion;
    try {
        runtimeVersion = (
            await execute(join(phpDirectory, 'php.exe'), ['-n', '-r', 'echo PHP_VERSION;'], {
                cwd: root,
                env: environment,
            })
        )
            .toString('utf8')
            .trim();
        if (runtimeVersion !== '8.5.12')
            throw new Error('The bundled PHP security/runtime version does not match.');
        const command = (args) =>
            execute(
                join(phpDirectory, 'php.exe'),
                [...phpArguments(phpDirectory), join(root, 'native/console.php'), ...args],
                { cwd: root, env: environment },
            );
        await command(['migrate', '--force', '--no-interaction']);
        if (!initialized) {
            // firstOrCreate/idempotent seed intent safely resumes interrupted first initialization;
            // a complete marker prevents later starts from restoring intentionally removed fixtures.
            await command(['db:seed', '--force', '--no-interaction']);
            const marker = await open(initializedPath, 'wx', 0o600);
            try {
                await marker.writeFile(initializedBytes);
                await marker.sync();
            } finally {
                await marker.close();
            }
        }
        ready = true;
    } catch (error) {
        await new Promise((done) => server.close(done));
        throw error;
    }
    return {
        origin,
        token,
        profile: absolute,
        fresh,
        phpVersion: runtimeVersion,
        async close() {
            if (stopped) return stopped;
            closing = true;
            stopped = (async () => {
                const drained = new Promise((done) => server.close(done));
                server.closeIdleConnections();
                await Promise.allSettled([...pending]);
                server.closeIdleConnections();
                await drained;
            })();
            return stopped;
        },
    };
}
