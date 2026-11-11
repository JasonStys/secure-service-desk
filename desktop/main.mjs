/**
 * File: desktop/main.mjs
 * Purpose: Launch the complete local service desk in a sandboxed native window with bundled PHP.
 * Functions: owned single instance, exact-origin session, real Laravel readiness and orderly exit;
 * see docs/code-index.md. No passwords, profiles or API keys are baked into this application.
 * State: the owned window/service, synthetic acceptance marker and coordinated startup/quit promises.
 */
import { app, BrowserWindow, dialog, Menu, shell } from 'electron';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readBounded, createNativeService, VERSION } from './runtime.mjs';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { openSync, fstatSync, readSync, closeSync, constants, lstatSync } from 'node:fs';

const sourceRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const acceptance = process.env.SSD_NATIVE_ACCEPTANCE_ROOT;
let service;
let window;
let quitting = false;
let exiting = false;
let startup;

// Set a verified synthetic userData path synchronously, before Electron can initialize normal caches.
if (acceptance) {
    const marker = join(resolve(acceptance), 'acceptance.marker');
    const descriptor = openSync(
        marker,
        constants.O_RDONLY | (constants.O_NOFOLLOW ?? 0) | (constants.O_NONBLOCK ?? 0),
    );
    try {
        const info = fstatSync(descriptor);
        const named = lstatSync(marker);
        const data = Buffer.alloc(129);
        const count = readSync(descriptor, data, 0, data.length, 0);
        if (
            !info.isFile() ||
            info.nlink !== 1 ||
            info.size > 128 ||
            named.isSymbolicLink() ||
            named.dev !== info.dev ||
            named.ino !== info.ino ||
            count !== info.size ||
            data.subarray(0, count).toString('utf8') !== 'ssd-owned-native-test\n'
        ) {
            throw new Error('Synthetic native marker refused.');
        }
        app.setPath('userData', join(resolve(acceptance), 'profile'));
    } finally {
        closeSync(descriptor);
    }
}

/** Acceptance uses only a caller-created synthetic marker, never the ordinary installed profile. */
async function chooseProfile() {
    if (!acceptance) return join(app.getPath('userData'), 'workspace');
    const root = resolve(acceptance);
    if (
        (await readBounded(join(root, 'acceptance.marker'), 128)).toString('utf8') !==
        'ssd-owned-native-test\n'
    ) {
        throw new Error('Native acceptance root is not owned.');
    }
    return join(root, 'profile');
}

/** Stop serving, retain actual PHP requests until completion and close only this app's windows/process. */
async function closeOwned() {
    if (quitting) return;
    quitting = true;
    if (window && !window.isDestroyed())
        window.setTitle('Secure Service Desk — closing after saved work completes');
    try {
        await startup;
        await service?.close();
    } catch {
        dialog.showErrorBox(
            'Service Desk close failed',
            'The local service did not close cleanly. Preserve your private profile before recovery.',
        );
    }
    exiting = true;
    app.quit();
}

app.on('before-quit', (event) => {
    if (!exiting) {
        event.preventDefault();
        void closeOwned();
    }
});
app.on('window-all-closed', () => {
    void closeOwned();
});

async function start() {
    try {
        if (!app.requestSingleInstanceLock()) {
            exiting = true;
            app.exit(2);
        } else {
            app.on('second-instance', () => {
                if (window) {
                    if (window.isMinimized()) window.restore();
                    window.focus();
                }
            });
            await app.whenReady();
            const profile = await chooseProfile();
            const resources = app.isPackaged
                ? join(process.resourcesPath, 'native')
                : join(sourceRoot, '.runtime/native-resources');
            window = new BrowserWindow({
                width: 1280,
                height: 900,
                minWidth: 920,
                minHeight: 650,
                title: `Secure Service Desk ${VERSION} — Jason Stys`,
                show: false,
                webPreferences: {
                    nodeIntegration: false,
                    contextIsolation: true,
                    sandbox: true,
                    webSecurity: true,
                    devTools: Boolean(acceptance),
                    backgroundThrottling: false,
                },
            });
            window.on('close', (event) => {
                if (!exiting) {
                    event.preventDefault();
                    void closeOwned();
                }
            });
            const startupPage =
                'data:text/html;charset=utf-8,' +
                encodeURIComponent(
                    '<!doctype html><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'"><title>Secure Service Desk — starting</title><body style="font:20px system-ui;background:#101b2d;color:#eef5ff;padding:48px"><h1>Secure Service Desk</h1><p>Starting your local ticket workspace…</p><p>First launch prepares the local database. Existing work is never reset. Closing waits for initialization to complete.</p><p>Created by Jason Stys · version 1.1.0 · offline local application</p></body>',
                );
            const owned = (value) => {
                try {
                    return Boolean(service) && new URL(value).origin === service.origin;
                } catch {
                    return false;
                }
            };
            const web = window.webContents;
            web.session.setPermissionRequestHandler((_contents, _permission, done) => done(false));
            web.session.setPermissionCheckHandler(() => false);
            web.session.webRequest.onBeforeRequest((details, done) =>
                done({ cancel: details.url !== startupPage && !owned(details.url) }),
            );
            web.session.webRequest.onBeforeSendHeaders((details, done) => {
                if (owned(details.url)) details.requestHeaders['X-SSD-Native'] = service.token;
                done({ requestHeaders: details.requestHeaders });
            });
            web.setWindowOpenHandler(() => ({ action: 'deny' }));
            web.on('will-navigate', (event, url) => {
                if (!owned(url)) {
                    event.preventDefault();
                    if (url === 'https://github.com/JasonStys')
                        void shell.openExternal(url).catch(() => {});
                }
            });
            await window.loadURL(startupPage);
            window.show();
            startup = createNativeService({
                root: join(resources, 'application'),
                phpDirectory: join(resources, 'php'),
                profile: join(profile, acceptance ? 'workspace' : ''),
            });
            service = await startup;
            if (quitting) throw new Error('Startup closed by its owner.');
            Menu.setApplicationMenu(
                Menu.buildFromTemplate([
                    {
                        label: 'Workspace',
                        submenu: [
                            {
                                label: 'Open ticket queue',
                                click: () => {
                                    if (!quitting)
                                        void window.loadURL(
                                            service.origin + '/?actor=agent@northwind.example',
                                        );
                                },
                            },
                            {
                                label: 'Exit after saved work completes',
                                click: () => {
                                    void closeOwned();
                                },
                            },
                        ],
                    },
                    {
                        label: 'Edit',
                        submenu: [
                            { role: 'undo' },
                            { role: 'redo' },
                            { type: 'separator' },
                            { role: 'cut' },
                            { role: 'copy' },
                            { role: 'paste' },
                            { role: 'selectAll' },
                        ],
                    },
                    {
                        label: 'Help',
                        submenu: [
                            {
                                label: 'How this local workspace works',
                                click: () => {
                                    void dialog.showMessageBox(window, {
                                        type: 'info',
                                        title: `Secure Service Desk ${VERSION} — Jason Stys`,
                                        message: 'The full ticket workspace, stored on this PC',
                                        detail: 'Create a ticket, switch the reference Agent/Requester view, assign an eligible owner, add comments and use valid lifecycle transitions. Refreshed versions prevent stale overwrites; the audit trail records committed changes. Search and filters retain the same tenant/requester access.\n\nPrepared fictional Northwind/Contoso data is initialized once inside this actual app. New tickets and changes persist in your OS-local profile across restarts. Actor selection is a single-OS-owner reference workflow, not production multiuser authentication. Do not place sensitive customer records here. No email, webhooks, cloud, scans or remote collectors run.\n\nExit waits for current local PHP requests. Preserve the whole profile after clean exit before backup or upgrade. The application never loads your development .env or database. Created by Jason Stys: github.com/JasonStys.',
                                    });
                                },
                            },
                            {
                                label: 'Source and contact',
                                click: () => {
                                    void shell
                                        .openExternal('https://github.com/JasonStys')
                                        .catch(() => {});
                                },
                            },
                        ],
                    },
                ]),
            );
            if (acceptance) {
                // Only synthetic acceptance exposes non-secret identity to its own test process.
                globalThis.__ssdNativeAcceptance = {
                    origin: service.origin,
                    version: VERSION,
                    profile,
                    packaged: app.isPackaged,
                    phpVersion: service.phpVersion,
                    asar: Object.fromEntries(
                        await Promise.all(
                            ['desktop/main.mjs', 'desktop/runtime.mjs'].map(async (name) => [
                                name,
                                createHash('sha256')
                                    .update(await readFile(join(app.getAppPath(), name)))
                                    .digest('hex'),
                            ]),
                        ),
                    ),
                };
            }
            await window.loadURL(service.origin + '/?actor=agent@northwind.example');
            window.show();
            window.setTitle(`Secure Service Desk ${VERSION} — Jason Stys`);
        }
    } catch {
        await app.whenReady();
        if (!acceptance && !quitting)
            dialog.showErrorBox(
                'Secure Service Desk could not start',
                'Close an existing instance or review local profile/runtime permissions. No saved workspace was reset.',
            );
        await service?.close();
        exiting = true;
        app.exit(2);
    }
}

// Do not top-level-await app.whenReady(): Electron must finish loading this ESM before emitting ready.
void start();
