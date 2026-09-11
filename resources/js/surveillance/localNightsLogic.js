// The decisions behind the "Nights on this device" list that are worth testing
// on their own: how the rows are cut into pages, and what the pager says.

/** Five nights is a week of watching; more than that is scrolled past, not read. */
export const NIGHTS_PER_PAGE = 5;

/**
 * One page of a list. The page asked for is clamped to what exists, so a page
 * emptied by a removal falls back to the last one that is left rather than
 * showing nothing, and an empty list is page 1 of 1 with no rows.
 *
 * @template T
 * @param {T[]} items
 * @param {number} page 1-based
 * @param {number} perPage
 * @returns {{ rows: T[], page: number, pageCount: number, from: number, to: number, total: number }}
 */
export function paginate(items, page, perPage = NIGHTS_PER_PAGE) {
    const total = items.length;
    const pageCount = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(Math.max(1, page), pageCount);
    const start = (current - 1) * perPage;
    const rows = items.slice(start, start + perPage);

    return {
        rows,
        page: current,
        pageCount,
        from: rows.length === 0 ? 0 : start + 1,
        to: start + rows.length,
        total,
    };
}

/** "Showing 6–10 of 12 nights", or nothing worth saying for a single page. */
export function pageSummary({ from, to, total }) {
    return `Showing ${from}–${to} of ${total} nights`;
}
