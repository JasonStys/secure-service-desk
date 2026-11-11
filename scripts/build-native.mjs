/**
 * File: scripts/build-native.mjs
 * Purpose: Package the real Laravel application, locked production dependencies and explicit PHP runtime.
 * Functions: checked commands, fresh source/resource staging and source/hash manifest; code-index.md.
 * State: explicit SDK inputs and public source/locked-dependency packaging outputs only.
 * This builder never installs, reads a user profile, starts Docker or publishes a release.
 */
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { createHash } from 'node:crypto';
import { cp, mkdir, readdir, lstat, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { phpArguments, readBounded, VERSION } from '../desktop/runtime.mjs';

const run = promisify(execFile);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = resolve(root, process.env.SSD_NATIVE_OUTPUT ?? 'release/native-candidate');
const resources = join(root, '.runtime/native-resources');
const phpSdk = resolve(process.env.SSD_PHP_SDK ?? '');
const crtSdk = resolve(process.env.SSD_CRT_SDK ?? '');
const composer = resolve(process.env.SSD_COMPOSER_PHAR ?? '');
const composerSha256 = '7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6';

/** Author a small ticket glyph directly as a native ICO; no external image, font or graphics package. */
async function writeIcon(path) {
    const side = 256;
    const pixels = Buffer.alloc(side * side * 4);
    for (let y = 0; y < side; y++) {
        for (let x = 0; x < side; x++) {
            const u = Math.floor(x / 4);
            const v = Math.floor(y / 4);
            const tile = u >= 3 && u < 61 && v >= 3 && v < 61;
            const paper = u >= 14 && u <= 49 && v >= 12 && v <= 51;
            const line =
                paper &&
                u >= 21 &&
                u <= 42 &&
                ((v >= 22 && v <= 25) || (v >= 32 && v <= 35) || (v >= 42 && v <= 45));
            const color =
                paper && !line ? [248, 253, 255, 255] : tile ? [118, 115, 0, 255] : [0, 0, 0, 0];
            Buffer.from(color).copy(pixels, ((side - 1 - y) * side + x) * 4);
        }
    }
    const bitmap = Buffer.alloc(40);
    bitmap.writeUInt32LE(40, 0);
    bitmap.writeInt32LE(side, 4);
    bitmap.writeInt32LE(side * 2, 8);
    bitmap.writeUInt16LE(1, 12);
    bitmap.writeUInt16LE(32, 14);
    bitmap.writeUInt32LE(pixels.length, 20);
    const image = Buffer.concat([bitmap, pixels, Buffer.alloc((side * side) / 8)]);
    const header = Buffer.alloc(22);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(1, 4);
    header[6] = 0;
    header[7] = 0; // ICO zero encodes 256 pixels.
    header.writeUInt16LE(1, 10);
    header.writeUInt16LE(32, 12);
    header.writeUInt32LE(image.length, 14);
    header.writeUInt32LE(22, 18);
    await writeFile(path, Buffer.concat([header, image]), { flag: 'wx' });
}

/** Walk only explicitly selected authored/runtime resources and reject linked files before packaging. */
async function inventory(directory) {
    const result = [];
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        const path = join(directory, entry.name);
        if (entry.isSymbolicLink()) throw new Error('Linked packaging resource refused.');
        if (entry.isDirectory()) result.push(...(await inventory(path)));
        else if (entry.isFile()) result.push(path);
        else throw new Error('Non-regular packaging resource refused.');
    }
    return result;
}

/** Invoke only fixed build tools with literal argument vectors and explicit bounded diagnostics. */
async function checked(executable, arguments_, options = {}) {
    const result = await run(executable, arguments_, {
        cwd: root,
        timeout: 180_000,
        maxBuffer: 2 * 1024 * 1024,
        windowsHide: true,
        ...options,
    });
    if (result.stdout) process.stdout.write(result.stdout);
    return result.stdout;
}

if (
    process.platform !== 'win32' ||
    !process.env.SSD_PHP_SDK ||
    !process.env.SSD_CRT_SDK ||
    !process.env.SSD_COMPOSER_PHAR
) {
    throw new Error(
        'Windows packaging needs explicit reviewed PHP SDK, app-local CRT and Composer PHAR paths.',
    );
}
if (
    !output.startsWith(join(root, 'release') + '\\') ||
    (await lstat(output).then(
        () => true,
        () => false,
    )) ||
    (await lstat(resources).then(
        () => true,
        () => false,
    ))
) {
    throw new Error(
        'Use fresh release and .runtime/native-resources paths; existing candidates are preserved.',
    );
}
const phpVersion = (
    await checked(join(phpSdk, 'php.exe'), ['-n', '-r', 'echo PHP_VERSION;'])
).trim();
if (phpVersion !== '8.5.12') throw new Error('Reviewed PHP8.5.12 runtime required.');
if (
    createHash('sha256')
        .update(await readBounded(composer, 8 * 1024 * 1024))
        .digest('hex') !== composerSha256
) {
    throw new Error('Composer2.10.3 official version-specific checksum mismatch.');
}
const metadata = JSON.parse(await readFile(join(root, 'package.json'), 'utf8'));
if (metadata.version !== VERSION || metadata.devDependencies.electron !== '44.11.0')
    throw new Error('Native product/runtime version mismatch.');
await checked(process.execPath, [join(root, 'node_modules/vite/bin/vite.js'), 'build']);
if (
    !(await lstat(join(root, '.runtime/desk.ico')).then(
        () => true,
        () => false,
    ))
)
    await writeIcon(join(root, '.runtime/desk.ico'));
await mkdir(join(resources, 'application'), { recursive: true });
await mkdir(join(resources, 'php/ext'), { recursive: true });
const application = join(resources, 'application');
for (const name of ['app', 'config', 'routes', 'native']) {
    await inventory(join(root, name));
    await cp(join(root, name), join(application, name), { recursive: true });
}
// Only authored database source subdirectories are read, never a development SQLite database.
await mkdir(join(application, 'database'));
for (const name of ['migrations', 'seeders', 'factories']) {
    await inventory(join(root, 'database', name));
    await cp(join(root, 'database', name), join(application, 'database', name), {
        recursive: true,
    });
}
await mkdir(join(application, 'bootstrap'));
for (const name of ['app.php', 'providers.php'])
    await cp(join(root, 'bootstrap', name), join(application, 'bootstrap', name));
await mkdir(join(application, 'public'));
await cp(join(root, 'public/build'), join(application, 'public/build'), { recursive: true });
await inventory(join(root, 'resources/views'));
await cp(join(root, 'resources/views'), join(application, 'resources/views'), { recursive: true });
for (const name of ['composer.json', 'composer.lock', 'LICENSE'])
    await cp(join(root, name), join(application, name));
const phpNames = [
    'php.exe',
    'php-cgi.exe',
    'php8.dll',
    'libsqlite3.dll',
    'libcrypto-3-x64.dll',
    'libssl-3-x64.dll',
    'license.txt',
    'readme-redist-bins.txt',
];
for (const name of phpNames) await cp(join(phpSdk, name), join(resources, 'php', name));
for (const name of ['mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'fileinfo'])
    await cp(join(phpSdk, 'ext', `php_${name}.dll`), join(resources, 'php/ext', `php_${name}.dll`));
for (const name of ['vcruntime140.dll', 'vcruntime140_1.dll'])
    await cp(join(crtSdk, name), join(resources, 'php', name));
const isolatedComposer = join(root, '.runtime/composer-home');
await mkdir(isolatedComposer, { recursive: true });
const composerEnvironment = Object.fromEntries(
    Object.entries(process.env).filter(([key]) =>
        [
            'PATH',
            'SYSTEMROOT',
            'WINDIR',
            'TEMP',
            'TMP',
            'COMSPEC',
            'HTTPS_PROXY',
            'HTTP_PROXY',
            'NO_PROXY',
            'SSL_CERT_FILE',
            'CURL_CA_BUNDLE',
        ].includes(key.toUpperCase()),
    ),
);
// Build-only Composer needs HTTPS downloads. These overrides never reach the application CGI.
await checked(
    join(phpSdk, 'php.exe'),
    [
        ...phpArguments(phpSdk),
        '-d',
        'extension=zip',
        '-d',
        'extension=curl',
        '-d',
        'allow_url_fopen=1',
        composer,
        'install',
        '--no-dev',
        '--no-scripts',
        '--no-plugins',
        '--no-interaction',
        '--prefer-dist',
        '--no-progress',
    ],
    {
        cwd: application,
        env: {
            ...composerEnvironment,
            COMPOSER_HOME: isolatedComposer,
            COMPOSER_AUTH: '{}',
            COMPOSER_ROOT_VERSION: VERSION,
            COMPOSER_CACHE_DIR: join(root, '.runtime/composer-cache'),
        },
    },
);
const source = (
    await checked('git', [
        '-c',
        `safe.directory=${root.replaceAll('\\', '/')}`,
        'rev-parse',
        'HEAD',
    ])
).trim();
const dirty = Boolean(
    (
        await checked('git', [
            '-c',
            `safe.directory=${root.replaceAll('\\', '/')}`,
            'status',
            '--porcelain',
        ])
    ).trim(),
);
if (!/^[a-f0-9]{40}$/.test(source)) throw new Error('Exact source identity required.');
const files = {};
for (const path of await inventory(resources)) {
    const data = await readBounded(path, 32 * 1024 * 1024);
    files[relative(resources, path).replaceAll('\\', '/')] = createHash('sha256')
        .update(data)
        .digest('hex');
}
await writeFile(
    join(resources, 'SOURCE.json'),
    JSON.stringify(
        {
            schema: 1,
            version: VERSION,
            source,
            sourceDirty: dirty,
            phpVersion,
            electronVersion: metadata.devDependencies.electron,
            asar: Object.fromEntries(
                await Promise.all(
                    ['desktop/main.mjs', 'desktop/runtime.mjs'].map(async (name) => [
                        name,
                        createHash('sha256')
                            .update(await readBounded(join(root, name), 128 * 1024))
                            .digest('hex'),
                    ]),
                ),
            ),
            files,
        },
        null,
        2,
    ) + '\n',
);
await checked(
    process.execPath,
    [
        join(root, 'node_modules/electron-builder/cli.js'),
        '--win',
        'dir',
        '--x64',
        '--publish',
        'never',
        '--config.directories.output=' + output,
    ],
    { timeout: 300_000 },
);
console.log(
    'Built complete unsigned portable candidate; actual packaged acceptance remains required.',
);
