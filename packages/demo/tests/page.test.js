// @vitest-environment happy-dom

// The demo page is static markup that capture.js drives, so what is worth pinning
// is where a panel sits rather than what it says.
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, test } from 'vitest';

// import.meta.url is not a file: URL under happy-dom, so the path is joined from
// import.meta.dirname instead.
const html = readFileSync(join(import.meta.dirname, '..', 'index.html'), 'utf8');
const page = new DOMParser().parseFromString(html, 'text/html');

const panelSaying = (text) =>
    [...page.querySelectorAll('.panel')].find((panel) => panel.textContent.includes(text)) ?? null;

describe('demo capture page', () => {
    // capture.js hides [data-capture="setup-help"] the moment a night starts. A
    // privacy claim parked in there would vanish for the whole night it describes.
    test('the privacy panel outlives the setup advice', () => {
        const panel = panelSaying('Nothing leaves this device');

        expect(panel).not.toBeNull();
        expect(page.querySelector('[data-capture="setup-help"]').contains(panel)).toBe(false);
    });

    test('the page makes that claim in one place, not two', () => {
        expect(html.match(/Detection runs entirely in this browser/g)).toHaveLength(1);
        expect(html.match(/Nothing leaves this device/g)).toHaveLength(1);
    });

    // The header capture.js drives sits on the camera; the state it writes has one
    // element to write to, and discarding belongs to the report, not to this page.
    test('the camera carries the night\'s header, and no discard button', () => {
        const header = page.querySelector('.tracker .tracker-header');

        expect(header).not.toBeNull();
        expect(header.querySelector('[data-capture="state"]')).not.toBeNull();
        expect(header.querySelector('[data-capture="idle-light"]')).not.toBeNull();
        expect(header.querySelector('[data-capture="started-at"]')).not.toBeNull();
        expect(header.querySelector('[data-capture="elapsed"]')).not.toBeNull();
        expect(page.querySelectorAll('[data-capture="elapsed"]')).toHaveLength(1);
        expect(page.querySelectorAll('[data-capture="state"]')).toHaveLength(1);
        expect(page.querySelector('[data-capture="abort"]')).toBeNull();
    });

    // An empty <video> is a black strip, so the stage stands in with the sample room
    // until a stream arrives. capture.js swaps the two by their hidden class, which
    // only works while both are on the page and start out that way round.
    test('the stage shows the sample room until the camera opens', () => {
        const stage = page.querySelector('.tracker .stage');
        const placeholder = stage.querySelector('[data-capture="placeholder"]');

        expect(placeholder).not.toBeNull();
        expect(placeholder.classList.contains('hidden')).toBe(false);
        expect(stage.querySelector('[data-capture="video"]').classList.contains('hidden')).toBe(true);
        expect(placeholder.querySelectorAll('[data-test="sample-room-trail"]')).toHaveLength(1);
    });

    // The numbers belong under the camera they were read off rather than in a side
    // panel, and capture.js writes each of them to a single element: a second copy
    // would sit there going stale all night.
    test('the night\'s numbers sit in a footer under the camera', () => {
        const stats = page.querySelector('.tracker .tracker-stats');

        expect(stats).not.toBeNull();
        expect(stats.previousElementSibling.classList.contains('stage')).toBe(true);

        for (const key of ['track-count', 'live-count', 'queue-depth', 'brightness']) {
            expect(page.querySelectorAll(`[data-capture="${key}"]`)).toHaveLength(1);
            expect(stats.querySelector(`[data-capture="${key}"]`)).not.toBeNull();
        }
    });
});
