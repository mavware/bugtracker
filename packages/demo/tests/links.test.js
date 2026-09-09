import { describe, expect, test } from 'vitest';
import { reportUrlFor } from '@bugtracker/surveillance';
import { INDEX_URL, nightIdFromSearch, REPORT_URL_TEMPLATE } from '../src/links.js';

describe('demo links', () => {
    test('the report template takes a night id the way the library swaps it in', () => {
        expect(reportUrlFor(REPORT_URL_TEMPLATE, 'abc-123')).toBe('report.html?night=abc-123');
    });

    test('the report page reads its night from the query string', () => {
        expect(nightIdFromSearch('?night=abc-123')).toBe('abc-123');
        expect(nightIdFromSearch('?night=')).toBeNull();
        expect(nightIdFromSearch('')).toBeNull();
    });

    test('links stay relative, so the site works from any subpath', () => {
        expect(REPORT_URL_TEMPLATE.startsWith('/')).toBe(false);
        expect(INDEX_URL).toBe('./');
    });
});
