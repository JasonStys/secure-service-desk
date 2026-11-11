/**
 * File: scripts/test-native-app.mjs
 * Purpose: Exercise actual packaged Electron/PHP/Laravel widgets, tenant roles and durable restart.
 * Functions: fresh explicitly marked synthetic profiles and real forms; docs/code-index.md.
 * State: actual owned app/window, synthetic test values, bounded diagnostics and normal-close proof.
 * No normal AppData, installed app, external accounts, global request headers or Docker are used.
 */
import { _electron as electron, expect } from '@playwright/test';
import { mkdtemp, mkdir, writeFile, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { once } from 'node:events';
import { execFileSync } from 'node:child_process';

if (process.argv.length > 2) {
    if (process.argv.length === 3 && process.argv[2] === '--help') {
        console.log(
            'Use SSD_NATIVE_OUTPUT=release/<fresh-candidate> and SSD_NATIVE_PROOF=artifacts/native/<new-proof>, then run this script without arguments. Only new marked synthetic profiles are used.',
        );
        process.exit(0);
    }
    throw new Error('Unknown native acceptance arguments; no app or profile was opened.');
}

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const candidate = resolve(root, process.env.SSD_NATIVE_OUTPUT ?? 'release/pilot-001');
const executable = process.env.SSD_NATIVE_EXECUTABLE
    ? resolve(process.env.SSD_NATIVE_EXECUTABLE)
    : join(candidate, 'win-unpacked/SecureServiceDesk.exe');
const output = resolve(root, process.env.SSD_NATIVE_PROOF ?? 'artifacts/native/pilot-001');
if (!output.startsWith(join(root, 'artifacts/native') + sep))
    throw new Error('Proof must stay below this project artifacts/native directory.');
await mkdir(dirname(output), { recursive: true });
await mkdir(output); // Preserve every earlier result; callers must select a fresh proof directory.
const scratch = await mkdtemp(join(tmpdir(), 'ssd-owned-native-'));
await writeFile(join(scratch, 'acceptance.marker'), 'ssd-owned-native-test\n', {
    flag: 'wx',
    mode: 0o600,
});
const metadata = JSON.parse(
    await readFile(join(dirname(executable), 'resources/native/SOURCE.json'), 'utf8'),
);
const expectedSource = execFileSync(
    'git',
    ['-c', `safe.directory=${root.replaceAll('\\', '/')}`, 'rev-parse', 'HEAD'],
    { cwd: root, encoding: 'utf8' },
).trim();
expect(metadata.source).toBe(expectedSource);
expect(typeof metadata.sourceDirty).toBe('boolean');
if (process.env.CI) expect(metadata.sourceDirty).toBe(false);
const expectedAsar = Object.fromEntries(
    await Promise.all(
        ['desktop/main.mjs', 'desktop/runtime.mjs'].map(async (name) => [
            name,
            createHash('sha256')
                .update(await readFile(join(root, name)))
                .digest('hex'),
        ]),
    ),
);
expect(metadata.asar).toEqual(expectedAsar);
const environment = Object.fromEntries(
    Object.entries(process.env).filter(([key]) =>
        ['SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP'].includes(key.toUpperCase()),
    ),
);
environment.PATH = join(process.env.SYSTEMROOT ?? 'C:/Windows', 'System32');
environment.SSD_NATIVE_ACCEPTANCE_ROOT = scratch;
const title = 'Calibration export needs review';
const comment = 'Reviewed the fictional sensor export; retain the checksum and replay report.';
const thirdParty = [];
const failures = [];
let lastPage;
let firstUrl;

/** Launch the actual bundled executable and wait for its real app, not a mocked/server-reused page. */
async function launch() {
    const application = await electron.launch({
        executablePath: executable,
        env: environment,
        cwd: scratch,
        timeout: 20_000,
    });
    active = application;
    const page = await application.firstWindow();
    lastPage = page;
    page.setDefaultTimeout(15_000);
    page.on('request', (request) => {
        const url = request.url();
        if (!url.startsWith('data:') && new URL(url).hostname !== '127.0.0.1')
            thirdParty.push(new URL(url).origin);
    });
    page.on('response', (response) => {
        if (response.status() >= 400)
            failures.push({ status: response.status(), path: new URL(response.url()).pathname });
    });
    page.on('console', (message) => {
        if (message.type() === 'error') failures.push({ console: message.text().slice(0, 500) });
    });
    await expect(page.getByRole('heading', { name: 'Signal Desk', exact: true })).toBeVisible({
        timeout: 60_000,
    });
    const identity = await application.evaluate(() => globalThis.__ssdNativeAcceptance);
    expect(identity.version).toBe('1.1.0');
    expect(identity.packaged).toBe(true);
    expect(identity.phpVersion).toBe('8.5.12');
    expect(identity.phpVersion).toBe(metadata.phpVersion);
    expect(identity.asar).toEqual(expectedAsar);
    expect(identity.profile.startsWith(scratch)).toBe(true);
    return { application, page, origin: identity.origin };
}

/** Actual window close must reach app quit after owned PHP work, not a force-kill helper. */
async function close(application) {
    const child = application.process();
    const exited = application.waitForEvent('close', { timeout: 60_000 });
    await application.evaluate(({ BrowserWindow }) => BrowserWindow.getAllWindows()[0].close());
    await exited;
    const [code] =
        child.exitCode === null
            ? await once(child, 'exit', { signal: AbortSignal.timeout(10_000) })
            : [child.exitCode];
    expect(code).toBe(0);
}

let active;
try {
    const first = await launch();
    active = first.application;
    const page = first.page;
    await expect(
        page.getByRole('complementary', { name: 'Local application information' }),
    ).toContainText('not a sign-in system');
    await expect(
        page.getByRole('complementary', { name: 'Local application information' }),
    ).toContainText('1.1.0');
    const denied = await fetch(first.origin + '/', { signal: AbortSignal.timeout(5000) });
    expect(denied.status).toBe(403);
    const malformedHeader = await fetch(first.origin + '/', {
        headers: { 'X-SSD-Native': '\u00e9'.repeat(64) },
        signal: AbortSignal.timeout(5000),
    });
    expect(malformedHeader.status).toBe(403);
    const unprotectedPost = await page.evaluate(
        async () =>
            (
                await fetch('/tickets?actor=agent@northwind.example', {
                    method: 'POST',
                    body: new URLSearchParams({ title: 'must not be created' }),
                })
            ).status,
    );
    expect(unprotectedPost).toBe(419);
    const executablePathAttempt = await page.evaluate(
        async () => (await fetch('/native/console.php')).status,
    );
    expect(executablePathAttempt).toBe(404);
    const cgiQueryAttempt = await page.evaluate(async () => (await fetch('/?-s')).status);
    expect(cgiQueryAttempt).toBe(400);
    await page.getByRole('link', { name: 'Requester', exact: true }).click();
    await page.getByLabel('Title', { exact: true }).fill(title);
    await page
        .getByLabel('Description', { exact: true })
        .fill(
            'The fictional calibration batch finished after its agreed reporting window. Review the local evidence before resending.',
        );
    await page.locator('form.ticket-form select[name="priority"]').selectOption('high');
    await page.locator('form.ticket-form input[name="tags[]"]').fill('calibration');
    await page.getByRole('button', { name: 'Create ticket', exact: true }).click();
    await page.getByRole('link', { name: title, exact: true }).click();
    firstUrl = new URL(page.url()).pathname;
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save assignment', exact: true })).toHaveCount(0);
    await page.goto(first.origin + firstUrl + '?actor=agent@northwind.example');
    const version = () =>
        page
            .locator('dl.metadata > div')
            .filter({ has: page.getByText('Version', { exact: true }) })
            .locator('dd');
    await expect(version()).toHaveText('1');
    await page
        .locator('form[aria-label="Ticket ownership"] select[name="assignee_id"]')
        .selectOption({ label: 'Morgan Agent' });
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    await expect(version()).toHaveText('2');
    await page
        .locator('form[aria-label="Ticket ownership"] input[name="version"]')
        .evaluate((input) => {
            input.value = '1';
        });
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    await expect(
        page.getByText('The ticket changed; refresh before assigning.', { exact: true }),
    ).toBeVisible();
    await expect(version()).toHaveText('2');
    await page.getByLabel('Comment', { exact: true }).fill(comment);
    await page.getByRole('button', { name: 'Add comment', exact: true }).click();
    await expect(page.getByText(comment, { exact: true })).toBeVisible();
    await page
        .locator('form[aria-label="Ticket lifecycle"] select[name="status"]')
        .selectOption('open');
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Move this ticket to open?');
        await dialog.dismiss();
    });
    await page.getByRole('button', { name: 'Update status', exact: true }).click();
    await expect(version()).toHaveText('2');
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Move this ticket to open?');
        await dialog.accept();
    });
    await page.getByRole('button', { name: 'Update status', exact: true }).click();
    await expect(version()).toHaveText('3');
    await page.screenshot({ path: join(output, 'ticket-workspace.png'), fullPage: false });
    await page.goto(first.origin + firstUrl + '?actor=requester@northwind.example');
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save assignment', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Update status', exact: true })).toHaveCount(0);
    await page.goto(first.origin + firstUrl + '?actor=agent@contoso.example');
    await expect(page.getByRole('heading', { name: title, exact: true })).toHaveCount(0);
    await page.goto(first.origin + '/?actor=agent@northwind.example');
    await page.getByLabel('Search', { exact: true }).fill('Calibration export');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();
    await expect(page.getByRole('link', { name: title, exact: true })).toBeVisible();
    await page.screenshot({ path: join(output, 'ticket-queue.png'), fullPage: false });
    await close(first.application);
    active = undefined;
    await expect(
        fetch(first.origin + '/', { signal: AbortSignal.timeout(3000) }),
    ).rejects.toThrow();
    const second = await launch();
    active = second.application;
    await second.page.goto(second.origin + firstUrl + '?actor=agent@northwind.example');
    await expect(second.page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await expect(second.page.getByText(comment, { exact: true })).toBeVisible();
    await expect(
        second.page
            .locator('dl.metadata > div')
            .filter({ has: second.page.getByText('Version', { exact: true }) })
            .locator('dd'),
    ).toHaveText('3');
    await close(second.application);
    active = undefined;
    await expect(
        fetch(second.origin + '/', { signal: AbortSignal.timeout(3000) }),
    ).rejects.toThrow();
    expect(thirdParty).toEqual([]);
    const receipt = {
        schema: 1,
        version: '1.1.0',
        source: metadata.source,
        sourceDirty: metadata.sourceDirty,
        bundledSourceSha256: expectedAsar,
        executableSha256: createHash('sha256')
            .update(await readFile(executable))
            .digest('hex'),
        launches: 2,
        actualPhp: metadata.phpVersion,
        create: true,
        missingCsrfRefused: true,
        invalidNonceRefused: true,
        fixedCgiScriptBoundary: true,
        cgiOptionQueryRefused: true,
        staleVersionRefused: true,
        assignmentVersion: 2,
        transitionVersion: 3,
        commentPersisted: true,
        requesterControlsRestricted: true,
        otherTenantRefused: true,
        queueSearch: true,
        restart: true,
        ownedHttpGone: true,
        thirdPartyRequests: 0,
        scope: 'Actual packaged EXE and real Laravel forms using only a new marked synthetic OS-local profile; no installed/default account data.',
    };
    await writeFile(
        join(output, 'native-acceptance.json'),
        JSON.stringify(receipt, null, 2) + '\n',
    );
    console.log(
        JSON.stringify({
            passed: true,
            launches: 2,
            actualPhp: metadata.phpVersion,
            product: 'secure-service-desk',
        }),
    );
} catch (error) {
    const visible =
        lastPage && !lastPage.isClosed()
            ? (
                  await lastPage
                      .locator('body')
                      .innerText()
                      .catch(() => '')
              ).slice(0, 4096)
            : '';
    await writeFile(
        join(output, 'failure.json'),
        JSON.stringify(
            { message: error.message, responses: failures, syntheticVisibleText: visible },
            null,
            2,
        ),
    );
    if (lastPage && !lastPage.isClosed())
        await lastPage
            .screenshot({ path: join(output, 'failure.png'), fullPage: true })
            .catch(() => {});
    throw error;
} finally {
    if (active) await close(active);
    // Failed/successful synthetic profiles remain private for review; never remove unknown paths.
}
