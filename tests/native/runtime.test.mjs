/**
 * File: tests/native/runtime.test.mjs
 * Purpose: Independently exercise bounded real files and the fixed CGI HTTP/argument contract.
 * Functions: temporary inert data, malformed responses and real hardlinks; docs/code-index.md.
 * State: new disposable test directories and fixed CGI responses, without application accounts.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, writeFile, link, rm, mkdir, readFile, readdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname, basename } from 'node:path';
import { parseCgi, phpArguments, readBounded, profileIdentity } from '../../desktop/runtime.mjs';

test('real native initialization retains identified data and refuses unknown nonempty profiles', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'ssd-native-files-'));
    try {
        const profile = join(directory, 'profile');
        const first = await profileIdentity(profile);
        assert.equal(first.fresh, true);
        assert.equal(first.identity.product, 'secure-service-desk');
        const marker = await readFile(join(profile, 'profile.json'));
        const database = join(profile, 'database.sqlite');
        await writeFile(database, 'synthetic existing bytes');
        const second = await profileIdentity(profile);
        assert.equal(second.fresh, false);
        assert.deepEqual(second.identity, first.identity);
        assert.deepEqual(await readFile(join(profile, 'profile.json')), marker);
        assert.equal(await readFile(database, 'utf8'), 'synthetic existing bytes');
        const unknown = join(directory, 'unknown');
        await mkdir(unknown);
        await writeFile(join(unknown, 'preserve.txt'), 'important synthetic bytes');
        await assert.rejects(profileIdentity(unknown), /Unknown nonempty/);
        assert.deepEqual(await readdir(unknown), ['preserve.txt']);
        assert.equal(
            await readFile(join(unknown, 'preserve.txt'), 'utf8'),
            'important synthetic bytes',
        );
        await writeFile(join(profile, 'profile.json'), '{}\n');
        await assert.rejects(profileIdentity(profile), /identity is invalid/);
        assert.equal(await readFile(database, 'utf8'), 'synthetic existing bytes');
    } finally {
        assert.equal(dirname(resolve(directory)), resolve(tmpdir()));
        assert.ok(basename(directory).startsWith('ssd-native-files-'));
        await rm(directory, { recursive: true });
    }
});

test('actual regular reads enforce bytes and reject hardlinks, directories and missing files', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'ssd-native-files-'));
    try {
        const input = join(directory, 'input');
        await writeFile(input, Buffer.from([0, 1, 2, 3]));
        assert.deepEqual(await readBounded(input, 4), Buffer.from([0, 1, 2, 3]));
        await assert.rejects(readBounded(input, 3));
        await assert.rejects(readBounded(directory, 128));
        await assert.rejects(readBounded(join(directory, 'missing'), 128));
        await link(input, join(directory, 'linked'));
        await assert.rejects(readBounded(input, 128));
        assert.deepEqual(
            await import('node:fs/promises').then((fs) => fs.readFile(input)),
            Buffer.from([0, 1, 2, 3]),
        );
    } finally {
        assert.equal(dirname(resolve(directory)), resolve(tmpdir()));
        assert.ok(basename(directory).startsWith('ssd-native-files-'));
        await rm(directory, { recursive: true });
    }
});

test('CGI preserves actual binary body and both session/CSRF cookies, removes server identities', () => {
    const raw = Buffer.concat([
        Buffer.from(
            'Status: 302 Found\r\nLocation: /tickets/abc?actor=a%40example.test\r\nSet-Cookie: session=one; HttpOnly\r\nSet-Cookie: XSRF=two\r\nX-Powered-By: PHP\r\nContent-Type: text/html\r\n\r\n',
        ),
        Buffer.from([0, 255, 10]),
    ]);
    const value = parseCgi(raw, 'http://127.0.0.1:34567');
    assert.equal(value.status, 302);
    assert.deepEqual(value.headers['set-cookie'], ['session=one; HttpOnly', 'XSRF=two']);
    assert.equal(value.headers.location, '/tickets/abc?actor=a%40example.test');
    assert.equal(value.headers['x-powered-by'], undefined);
    assert.deepEqual(value.body, Buffer.from([0, 255, 10]));
});

for (const [name, raw] of [
    ['no separator', 'Content-Type: text/plain'],
    ['outside redirect', 'Location: https://outside.example/\n\nsecret'],
    ['network-relative redirect', 'Location: //outside.example/\n\nsecret'],
    ['different loopback port', 'Location: http://127.0.0.1:99/\n\nsecret'],
    ['invalid status', 'Status: 101 Switching Protocols\n\nbody'],
    ['ambiguous status', 'Status: 200 OK\nStatus: 403 Forbidden\n\nbody'],
    ['malformed header', 'not-a-header\n\nbody'],
    ['control byte', 'Content-Type: text/\u0000plain\n\nbody'],
    ['duplicate location', 'Location: /one\nLocation: /two\n\nbody'],
    ['oversized header', 'Ignored: ' + 'x'.repeat(32769) + '\n\nbody'],
]) {
    test('CGI rejects ' + name, () =>
        assert.throws(() => parseCgi(Buffer.from(raw), 'http://127.0.0.1:34567')),
    );
}

test('PHP argv has fixed standalone ini/script policy and no caller input, shell or network URL', () => {
    const args = phpArguments('C:/owned runtime', true);
    assert.equal(args[0], '-n');
    assert.ok(
        args.includes('extension_dir=C:/owned runtime/ext') ||
            args.includes('extension_dir=C:\\owned runtime\\ext'),
    );
    assert.ok(args.includes('allow_url_fopen=0'));
    assert.ok(args.includes('allow_url_include=0'));
    assert.ok(args.includes('cgi.force_redirect=0'));
    assert.ok(args.includes('cgi.fix_pathinfo=0'));
    assert.equal(
        args.some((value) => value.includes('http:') || value.includes('https:')),
        false,
    );
});
