// The decisions behind the shared confirm dialog, kept apart from the DOM so
// they can be pinned in the node environment.

export const DEFAULT_ACCEPT_LABEL = 'Confirm';
export const DEFAULT_CANCEL_LABEL = 'Cancel';
export const FALLBACK_MESSAGE = 'Are you sure?';

/**
 * Split a confirm message into the question the dialog leads with and the
 * consequence it explains underneath. Copy across the app reads "Delete this
 * session? Its images go with it.", so the first question mark followed by a
 * break, or the first line break, is where the heading ends. A message with
 * neither is all heading.
 *
 * @param {string} message
 * @returns {{ title: string, detail: string }}
 */
export function splitConfirmMessage(message) {
    const text = String(message ?? '').replaceAll('\\n', '\n').trim();

    if (text === '') {
        return { title: FALLBACK_MESSAGE, detail: '' };
    }

    const match = text.match(/^(.*?\?)\s+(\S[\s\S]*)$/) ?? text.match(/^([^\n]*)\n+([\s\S]*)$/);

    if (match === null) {
        return { title: text, detail: '' };
    }

    return { title: match[1].trim(), detail: match[2].trim() };
}

/**
 * The options a wire:confirm trigger can carry as data attributes, read off the
 * element so Blade decides the label and tone, not the script.
 *
 * @param {{ dataset?: DOMStringMap }} el
 * @returns {{ confirmLabel: string|undefined, destructive: boolean }}
 */
export function confirmOptionsFrom(el) {
    const dataset = el?.dataset ?? {};

    return {
        confirmLabel: dataset.confirmLabel || undefined,
        destructive: 'confirmDestructive' in dataset,
    };
}
