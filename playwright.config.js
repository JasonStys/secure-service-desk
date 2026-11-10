/**
 * File: Actual browser acceptance configuration for native PHP/SQLite and built production assets.
 * Symbols: port/baseURL and exported Playwright configuration.
 * State: workers=1 isolates shared synthetic fixtures; unknown existing servers are never reused.
 */
import { defineConfig, devices } from '@playwright/test';
const rawPort = process.env.SSD_BROWSER_PORT ?? '18971';
const port = Number(rawPort);
if (!/^\d{4,5}$/.test(rawPort) || !Number.isSafeInteger(port) || port < 1024 || port > 65535)
    throw new Error('SSD_BROWSER_PORT must be 1024..65535');
const baseURL = `http://127.0.0.1:${port}`;
export default defineConfig({
    testDir: './tests/browser',
    testMatch: '**/*.browser.js',
    workers: 1,
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: 0,
    timeout: 30000,
    expect: { timeout: 5000 },
    outputDir: 'build/browser-results',
    reporter: [['list'], ['json', { outputFile: 'build/browser-results.json' }]],
    use: { baseURL, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
    ],
    webServer: {
        command: 'node tests/browser/server.js',
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 120000,
    },
});
