/**
 * File: Actual server-rendered ticket workspace journeys with real PHP, SQLite, sessions and CSRF.
 * Symbols: fixture manifest, createThroughForm, apiTicket, filterQueue and browser scenarios.
 * State: unique synthetic tickets per mutation; shared SLA/history fixtures are read-only.
 */
import { randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const AGENT = 'agent@northwind.example';
const REQUESTER = 'requester@northwind.example';
const OUTSIDER = 'agent@contoso.example';
const actors = (email) => `actor=${encodeURIComponent(email)}`;
const manifest = () => readFile('build/browser-fixture.json', 'utf8').then(JSON.parse);

/** Create real work through a normal CSRF form, then find its actual workspace link in the returned queue. */
async function createThroughForm(page, title = `Browser request ${randomUUID()}`) {
    await page.goto(`/?${actors(REQUESTER)}`);
    const form = page.locator('form.ticket-form');
    await form.getByLabel('Title', { exact: true }).fill(title);
    await form
        .getByLabel('Description', { exact: true })
        .fill('Scheduled field report shows <b>literal text</b>; reference reading is 12.5 mA.');
    await form.getByRole('combobox', { name: 'Priority', exact: true }).selectOption('normal');
    await form.getByRole('button', { name: 'Create ticket', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('is ready.');
    const link = page.getByRole('link', { name: title, exact: true });
    const href = await link.getAttribute('href');
    if (!href) throw new Error('created workspace link absent');
    const id = Number(new URL(href).pathname.split('/').at(-1));
    expect(Number.isSafeInteger(id)).toBe(true);
    return { id, title };
}

/** Read actual scoped API state as an independent oracle; no response bodies are mocked. */
async function apiTicket(request, id, actor = AGENT) {
    const response = await request.get('/api/v1/tickets?limit=100', {
        headers: { 'X-Demo-Actor': actor },
    });
    expect(response.ok()).toBe(true);
    const body = await response.json();
    const ticket = body.data.find((item) => item.id === id);
    if (!ticket) throw new Error(`fixture ticket ${id} not visible in bounded API oracle`);
    return ticket;
}

/** Apply actual GET filters across all visible records, not the optional current-page live preview. */
async function filterQueue(page, values) {
    const form = page.locator('form[data-filter-form]');
    await form.getByLabel('Search', { exact: true }).fill(values.q ?? 'Fixture SLA');
    await form
        .getByRole('combobox', { name: 'Owner', exact: true })
        .selectOption(values.owner ?? '');
    await form
        .getByRole('combobox', { name: 'SLA window', exact: true })
        .selectOption(values.due ?? '');
    await form.getByRole('button', { name: 'Apply', exact: true }).click();
}

test('requester creates a plain-text request with blank optional tags and can comment but not assign', async ({
    page,
    request,
}) => {
    const created = await createThroughForm(page);
    await page.getByRole('link', { name: created.title, exact: true }).click();
    await expect(page.getByRole('heading', { name: created.title, exact: true })).toBeVisible();
    await expect(
        page.getByText(
            'Scheduled field report shows <b>literal text</b>; reference reading is 12.5 mA.',
            { exact: true },
        ),
    ).toBeVisible();
    await expect(page.locator('b')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Save assignment', exact: true })).toHaveCount(0);
    await page
        .getByLabel('Comment', { exact: true })
        .fill('Reference checked: 12.5 mA; awaiting the next export.');
    await page.getByRole('button', { name: 'Add comment', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Comment added.');
    await expect(
        page.getByText('Reference checked: 12.5 mA; awaiting the next export.', { exact: true }),
    ).toBeVisible();
    expect(await apiTicket(request, created.id, REQUESTER)).toMatchObject({
        version: 1,
        status: 'new',
        assignee_id: null,
        tags: [],
    });
});

test('agent assignment preserves SLA, rejects stale/foreign ownership and avoids duplicate no-op evidence', async ({
    page,
    request,
    context,
}) => {
    const fixture = await manifest();
    const created = await createThroughForm(page);
    const initial = await apiTicket(request, created.id);
    await page.goto(`/tickets/${created.id}?${actors(AGENT)}`);
    const stale = await context.newPage();
    await stale.goto(`/tickets/${created.id}?${actors(AGENT)}`);
    await page
        .getByRole('combobox', { name: 'Assign to', exact: true })
        .selectOption(String(fixture.users.avery));
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Ticket assignment saved.');
    const assigned = await apiTicket(request, created.id);
    expect(assigned).toMatchObject({
        version: 2,
        assignee_id: fixture.users.avery,
        status: initial.status,
        sla_due_at: initial.sla_due_at,
    });
    await stale
        .getByRole('combobox', { name: 'Assign to', exact: true })
        .selectOption(String(fixture.users.agent));
    await stale.getByRole('button', { name: 'Save assignment', exact: true }).click();
    await expect(stale.getByRole('alert')).toContainText(
        'ticket changed; refresh before assigning',
    );
    expect(await apiTicket(request, created.id)).toEqual(assigned);
    await page.reload();
    const audit = page.getByRole('region', { name: 'Audit history', exact: true });
    await expect(audit.locator('ol > li')).toHaveCount(2);
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    expect(await apiTicket(request, created.id)).toEqual(assigned);
    await expect(audit.locator('ol > li')).toHaveCount(2);
    await page.getByRole('combobox', { name: 'Assign to', exact: true }).evaluate((select, id) => {
        select.add(new Option('Other tenant actor', String(id)));
        select.value = String(id);
    }, fixture.users.outsider);
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('eligible service agent in this tenant');
    expect(await apiTicket(request, created.id)).toEqual(assigned);
    await expect(audit.locator('ol > li')).toHaveCount(2);
    await stale.close();
});

test('resolved ownership is disabled until an explicit legal reopen and unassignment keeps the same SLA', async ({
    page,
    request,
}) => {
    const created = await createThroughForm(page);
    const initial = await apiTicket(request, created.id);
    await page.goto(`/tickets/${created.id}?${actors(AGENT)}`);
    const fixture = await manifest();
    await page
        .getByRole('combobox', { name: 'Assign to', exact: true })
        .selectOption(String(fixture.users.agent));
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Ticket assignment saved.');
    for (const status of ['open', 'resolved']) {
        await page.getByRole('combobox', { name: 'Move to', exact: true }).selectOption(status);
        page.once('dialog', (dialog) => {
            expect(dialog.message()).toContain(`Move this ticket to ${status}`);
            void dialog.accept();
        });
        await page.getByRole('button', { name: 'Update status', exact: true }).click();
        await expect(page.getByRole('status')).toHaveText('Ticket status updated.');
    }
    await expect(page.getByRole('button', { name: 'Save assignment', exact: true })).toHaveCount(0);
    await expect(page.getByText(/Assignment is disabled for resolved\/closed work/u)).toBeVisible();
    await page.getByRole('combobox', { name: 'Move to', exact: true }).selectOption('open');
    page.once('dialog', (dialog) => {
        void dialog.accept();
    });
    await page.getByRole('button', { name: 'Update status', exact: true }).click();
    await page.getByRole('combobox', { name: 'Assign to', exact: true }).selectOption('');
    await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
    expect(await apiTicket(request, created.id)).toMatchObject({
        status: 'open',
        version: 6,
        assignee_id: null,
        sla_due_at: initial.sla_due_at,
    });
});

test('owner/SLA queue filters exclude resolved work and never expand requester access', async ({
    page,
}) => {
    await page.goto(`/?${actors(AGENT)}`);
    await filterQueue(page, { owner: 'mine', due: 'overdue' });
    await expect(page.locator('[data-ticket-card]')).toHaveCount(1);
    await expect(
        page.getByRole('link', { name: 'Fixture SLA overdue', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Fixture SLA resolved', exact: true })).toHaveCount(
        0,
    );
    await filterQueue(page, { owner: 'unassigned', due: 'due-soon' });
    await expect(page.locator('[data-ticket-card]')).toHaveCount(1);
    await expect(
        page.getByRole('link', { name: 'Fixture SLA due soon', exact: true }),
    ).toBeVisible();
    await page.goto(`/?${actors(REQUESTER)}`);
    await filterQueue(page, { owner: 'mine', due: 'overdue' });
    await expect(page.getByText('No tickets match these filters.', { exact: true })).toBeVisible();
    await expect(page.getByText('Other requester private fixture', { exact: true })).toHaveCount(0);
});

test('requester and other tenant cannot read or mutate known foreign workspaces with valid session tokens', async ({
    page,
    request,
}) => {
    const fixture = await manifest();
    for (const [actor, id] of [
        [REQUESTER, fixture.tickets.private],
        [OUTSIDER, fixture.tickets.overdue],
    ]) {
        const response = await page.goto(`/tickets/${id}?${actors(actor)}`);
        expect(response?.status()).toBe(404);
        await expect(
            page.getByText('Other requester private fixture', { exact: true }),
        ).toHaveCount(0);
        await page.goto(`/?${actors(actor)}`);
        const token = await page.locator('form.ticket-form input[name="_token"]').inputValue();
        const denied = await page.request.post(`/tickets/${id}/comments?${actors(actor)}`, {
            form: { _token: token, body: 'Must not be appended' },
            maxRedirects: 0,
        });
        expect(denied.status()).toBe(404);
    }
    const before = await apiTicket(request, fixture.tickets.overdue);
    const denied = await request.post(`/api/v1/tickets/${fixture.tickets.overdue}/assignment`, {
        headers: { 'X-Demo-Actor': REQUESTER },
        data: { assignee_id: fixture.users.agent, version: before.version },
    });
    expect(denied.status()).toBe(403);
    expect(await apiTicket(request, fixture.tickets.overdue)).toEqual(before);
});

test('real CSRF fallback rejects missing/wrong tokens and accepts the actual session form token', async ({
    page,
    request,
}) => {
    await page.goto(`/?${actors(REQUESTER)}`);
    const token = await page.locator('form.ticket-form input[name="_token"]').inputValue();
    expect(token.length).toBeGreaterThan(20);
    const headers = { 'X-Demo-Actor': REQUESTER };
    const before = await (await request.get('/api/v1/tickets?limit=100', { headers })).json();
    const data = {
        title: `Browser CSRF ${randomUUID()}`,
        description: 'Synthetic session-form CSRF fallback check.',
        priority: 'normal',
        request_key: `csrf-${randomUUID()}`,
    };
    for (const attempted of [{ ...data }, { ...data, _token: 'wrong-session-token' }]) {
        const denied = await page.request.post(`/tickets?${actors(REQUESTER)}`, {
            form: attempted,
            headers: { 'Sec-Fetch-Site': 'cross-site' },
            maxRedirects: 0,
        });
        expect(denied.status()).toBe(419);
    }
    const unchanged = await (await request.get('/api/v1/tickets?limit=100', { headers })).json();
    expect(unchanged).toEqual(before);
    const accepted = await page.request.post(`/tickets?${actors(REQUESTER)}`, {
        form: { ...data, _token: token },
        headers: { 'Sec-Fetch-Site': 'cross-site' },
        maxRedirects: 0,
    });
    expect(accepted.status()).toBe(302);
    const after = await (await request.get('/api/v1/tickets?limit=100', { headers })).json();
    expect(after.data).toHaveLength(before.data.length + 1);
    expect(after.data.filter((ticket) => ticket.title === data.title)).toHaveLength(1);
});

test('comment and audit pagination are independent bounded real history windows', async ({
    page,
}) => {
    const fixture = await manifest();
    await page.goto(`/tickets/${fixture.tickets.history}?${actors(REQUESTER)}`);
    const comments = page.getByRole('region', { name: 'Discussion', exact: true });
    const audit = page.getByRole('region', { name: 'Audit history', exact: true });
    await expect(comments.locator('ol > li')).toHaveCount(20);
    await expect(audit.locator('ol > li')).toHaveCount(20);
    await page.getByRole('link', { name: 'Older comments', exact: true }).click();
    await expect(comments.locator('ol > li')).toHaveCount(1);
    await expect(audit.locator('ol > li')).toHaveCount(20);
    await expect(comments).toContainText('Inspection note 01:');
    await page.getByRole('link', { name: 'Older audit events', exact: true }).click();
    await expect(comments.locator('ol > li')).toHaveCount(1);
    await expect(audit.locator('ol > li')).toHaveCount(2);
    const url = new URL(page.url());
    expect(url.searchParams.get('comments_page')).toBe('2');
    expect(url.searchParams.get('audit_page')).toBe('2');
    expect(url.searchParams.get('actor')).toBe(REQUESTER);
    await page.getByRole('link', { name: 'Newer comments', exact: true }).click();
    await expect(comments.locator('ol > li')).toHaveCount(20);
    await expect(audit.locator('ol > li')).toHaveCount(2);
});

for (const route of ['queue', 'workspace']) {
    test(`actual ${route} has no detectable WCAG A/AA problems or viewport-wide overflow`, async ({
        page,
    }) => {
        const fixture = await manifest();
        await page.goto(
            route === 'queue'
                ? `/?${actors(AGENT)}`
                : `/tickets/${fixture.tickets.history}?${actors(AGENT)}`,
        );
        const width = await page.evaluate(() => ({
            viewport: document.documentElement.clientWidth,
            content: document.documentElement.scrollWidth,
        }));
        expect(width.content).toBeLessThanOrEqual(width.viewport);
        expect(
            (
                await new AxeBuilder({ page })
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
                    .analyze()
            ).violations,
        ).toEqual([]);
    });
}

test('compiled enhancement filters the current page and canceling confirmation leaves state untouched', async ({
    page,
    request,
}) => {
    const created = await createThroughForm(page);
    const stylesheet = await page.locator('link[rel="stylesheet"]').first().getAttribute('href');
    const script = await page.locator('script[type="module"][src]').first().getAttribute('src');
    expect(new URL(stylesheet).pathname).toMatch(/^\/build\/assets\/app-[A-Za-z0-9_-]+\.css$/);
    expect(new URL(script).pathname).toMatch(/^\/build\/assets\/app-[A-Za-z0-9_-]+\.js$/);
    const count = await page.locator('[data-ticket-card]').count();
    const search = page.locator('form[data-filter-form]').getByLabel('Search', { exact: true });
    await search.fill(created.title);
    await expect(page.locator('[data-ticket-card]:visible')).toHaveCount(1);
    await search.fill('');
    await expect(page.locator('[data-ticket-card]:visible')).toHaveCount(count);
    const before = await apiTicket(request, created.id);
    await page.goto(`/tickets/${created.id}?${actors(AGENT)}`);
    await page.getByRole('combobox', { name: 'Move to', exact: true }).selectOption('open');
    let confirmed = false;
    page.once('dialog', (dialog) => {
        expect(dialog.message()).toContain('Move this ticket to open');
        confirmed = true;
        void dialog.dismiss();
    });
    await page.getByRole('button', { name: 'Update status', exact: true }).click();
    expect(confirmed).toBe(true);
    expect(await apiTicket(request, created.id)).toEqual(before);
});

test.describe('no JavaScript progressive forms', () => {
    test.use({ javaScriptEnabled: false });
    test('request creation, discussion and assignment work without the enhancement script', async ({
        page,
        request,
    }) => {
        const fixture = await manifest();
        const created = await createThroughForm(page);
        await page.goto(`/tickets/${created.id}?${actors(REQUESTER)}`);
        await page
            .getByLabel('Comment', { exact: true })
            .fill('No-script field review is recorded.');
        await page.getByRole('button', { name: 'Add comment', exact: true }).click();
        await expect(
            page.getByText('No-script field review is recorded.', { exact: true }),
        ).toBeVisible();
        await page.goto(`/tickets/${created.id}?${actors(AGENT)}`);
        await page
            .getByRole('combobox', { name: 'Assign to', exact: true })
            .selectOption(String(fixture.users.agent));
        await page.getByRole('button', { name: 'Save assignment', exact: true }).click();
        expect(await apiTicket(request, created.id)).toMatchObject({
            assignee_id: fixture.users.agent,
            version: 2,
        });
    });
});
