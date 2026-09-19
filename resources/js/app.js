/**
 * File: Adds optional live filtering and transition confirmation to server-rendered forms.
 * Functions: normaliseSearch(), ticketMatches(), and initialiseDashboard().
 * State: DOM form/card references only; exact symbol locations are indexed in docs/code-index.md.
 */

/** Normalize user-visible text for case-insensitive matching. */
export function normaliseSearch(value) {
    return String(value ?? '').normalize('NFKC').trim().toLocaleLowerCase('en-US');
}

/** Determine whether searchable ticket text contains every query token. */
export function ticketMatches(searchableText, query) {
    const haystack = normaliseSearch(searchableText);
    const tokens = normaliseSearch(query).split(/\s+/u).filter(Boolean).slice(0, 10);
    return tokens.every((token) => haystack.includes(token));
}

/** Attach unobtrusive dashboard behavior when the page provides matching controls. */
export function initialiseDashboard(root = document) {
    const search = root.querySelector('[data-live-search]');
    const cards = [...root.querySelectorAll('[data-ticket-card]')];
    search?.addEventListener('input', () => {
        cards.forEach((card) => {
            card.hidden = !ticketMatches(card.dataset.search, search.value);
        });
    });

    root.querySelectorAll('[data-transition-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const status = form.querySelector('[name="status"]')?.value ?? 'the selected state';
            if (!globalThis.confirm(`Move this ticket to ${status}?`)) {
                event.preventDefault();
            }
        });
    });
}

if (typeof document !== 'undefined') {
    initialiseDashboard(document);
}
