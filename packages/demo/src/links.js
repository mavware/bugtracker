// How the demo's two pages find each other. The report page is addressed by a
// query string, which GitHub Pages serves without any routing of its own.
import { LOCAL_ID_PLACEHOLDER } from '@bugtracker/surveillance';

/** The template localNight.reportUrlFor swaps a night's id into. */
export const REPORT_URL_TEMPLATE = `report.html?night=${LOCAL_ID_PLACEHOLDER}`;

export const INDEX_URL = './';

/** The night a report page is for, or null when the address names none. */
export function nightIdFromSearch(search) {
    const id = new URLSearchParams(search).get('night');

    return id !== null && id !== '' ? id : null;
}
