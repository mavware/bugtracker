import { describe, expect, test } from 'vitest';
import { NIGHTS_PER_PAGE, paginate, pageSummary } from '../../../resources/js/surveillance/localNightsLogic.js';

describe('paginate', () => {
    const items = Array.from({ length: 12 }, (_, i) => i + 1);

    test('cuts five to a page by default', () => {
        expect(NIGHTS_PER_PAGE).toBe(5);
        expect(paginate(items, 1)).toMatchObject({ rows: [1, 2, 3, 4, 5], page: 1, pageCount: 3, from: 1, to: 5, total: 12 });
        expect(paginate(items, 3)).toMatchObject({ rows: [11, 12], page: 3, from: 11, to: 12 });
    });

    test('clamps a page that no longer exists to the last one, and never below the first', () => {
        expect(paginate(items, 9).page).toBe(3);
        expect(paginate(items, 0).page).toBe(1);
        expect(paginate(items, -4).rows).toEqual([1, 2, 3, 4, 5]);
    });

    test('an empty list is one empty page', () => {
        expect(paginate([], 1)).toEqual({ rows: [], page: 1, pageCount: 1, from: 0, to: 0, total: 0 });
    });

    test('exactly one full page is one page', () => {
        expect(paginate(items.slice(0, 5), 1).pageCount).toBe(1);
    });
});

describe('pageSummary', () => {
    test('says which nights are on show', () => {
        expect(pageSummary({ from: 6, to: 10, total: 12 })).toBe('Showing 6–10 of 12 nights');
    });
});
