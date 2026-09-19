/**
 * File: Verifies deterministic client-side ticket search behavior without a browser runtime.
 * Symbols: four node:test cases for normalization, token matching, Unicode, and token bounds.
 * State: test-only input strings and boolean assertions; exact locations are in docs/code-index.md.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { normaliseSearch, ticketMatches } from '../../resources/js/app.js';

test('normaliseSearch trims and folds case', () => {
    assert.equal(normaliseSearch('  HIGH Priority  '), 'high priority');
});

test('ticketMatches requires every query token', () => {
    assert.equal(ticketMatches('critical telemetry export', 'telemetry critical'), true);
    assert.equal(ticketMatches('critical telemetry export', 'telemetry billing'), false);
});

test('ticketMatches normalizes compatible Unicode', () => {
    assert.equal(ticketMatches('Ｆｉｅｌｄ report', 'field'), true);
});

test('ticketMatches bounds query work to ten tokens', () => {
    const haystack = 'one two three four five six seven eight nine ten';
    assert.equal(ticketMatches(haystack, `${haystack} absent ignored`), true);
});
