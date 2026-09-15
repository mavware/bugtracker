// The one confirm dialog the layouts render (x-confirm-dialog), asked the way
// window.confirm is asked but drawn in the app's own clothes. Every "are you
// sure?" in the app goes through here: page scripts call confirmDialog()
// directly, and wire:confirm buttons are routed through it by
// themeLivewireConfirms(), so a browser-native box never appears over a page
// that has the dialog. A page without the dialog falls back to window.confirm,
// so nothing depends on the layout having it.
import {
    confirmOptionsFrom,
    DEFAULT_ACCEPT_LABEL,
    DEFAULT_CANCEL_LABEL,
    FALLBACK_MESSAGE,
    splitConfirmMessage,
} from './confirmDialogLogic.js';

const ACCEPT_VALUE = 'confirm';

function findDialog() {
    return document.querySelector('dialog[data-confirm-dialog]');
}

/**
 * Ask the question and resolve true when the user accepts. Cancel, Escape and
 * a click outside all resolve false.
 *
 * @param {string} message The question, optionally followed by its consequence.
 * @param {{ title?: string, detail?: string, confirmLabel?: string, cancelLabel?: string, destructive?: boolean }} [options]
 * @returns {Promise<boolean>}
 */
export function confirmDialog(message, options = {}) {
    const dialog = findDialog();

    if (dialog === null) {
        return Promise.resolve(window.confirm(message || FALLBACK_MESSAGE));
    }

    // A second question while one is open has no room to be asked; showModal()
    // on an open dialog throws, so answer no rather than fall over.
    if (dialog.open) {
        return Promise.resolve(false);
    }

    const split = splitConfirmMessage(message);
    const part = (name) => dialog.querySelector(`[data-confirm="${name}"]`);
    const destructive = options.destructive === true;

    part('title').textContent = options.title ?? split.title;

    const detail = options.detail ?? split.detail;
    part('detail').textContent = detail;
    part('detail').classList.toggle('hidden', detail === '');

    part('icon').classList.toggle('is-destructive', destructive);
    part('accept').classList.toggle('hidden', destructive);
    part('accept-danger').classList.toggle('hidden', !destructive);

    const acceptLabel = options.confirmLabel ?? DEFAULT_ACCEPT_LABEL;
    part('accept-label').textContent = acceptLabel;
    part('accept-danger-label').textContent = acceptLabel;
    part('cancel-label').textContent = options.cancelLabel ?? DEFAULT_CANCEL_LABEL;

    return new Promise((resolve) => {
        const onAccept = () => dialog.close(ACCEPT_VALUE);
        const onCancel = () => dialog.close('');
        const onBackdrop = (event) => {
            // The dialog element covers only the panel; a click whose target is
            // the dialog itself landed on the backdrop.
            if (event.target === dialog) {
                dialog.close('');
            }
        };
        const onClose = () => {
            part('accept').removeEventListener('click', onAccept);
            part('accept-danger').removeEventListener('click', onAccept);
            part('cancel').removeEventListener('click', onCancel);
            dialog.removeEventListener('click', onBackdrop);
            resolve(dialog.returnValue === ACCEPT_VALUE);
        };

        part('accept').addEventListener('click', onAccept);
        part('accept-danger').addEventListener('click', onAccept);
        part('cancel').addEventListener('click', onCancel);
        dialog.addEventListener('click', onBackdrop);
        dialog.addEventListener('close', onClose, { once: true });
        dialog.returnValue = '';
        dialog.showModal();
    });
}

/**
 * Route every wire:confirm through the themed dialog. Livewire will not let a
 * directive be registered twice, but its own handler only sets
 * el.__livewire_confirm, and directive.init hooks run in registration order, so
 * one registered at livewire:init runs after Livewire's and can replace that
 * function with one that asks the dialog instead. wire:confirm.prompt keeps
 * Livewire's behaviour: it wants typed text, which this dialog does not take.
 *
 * @param {{ hook: (name: string, callback: Function) => void }} livewire
 */
export function themeLivewireConfirms(livewire) {
    livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm' || directive.modifiers.includes('prompt')) {
            return;
        }

        const message = directive.expression.replaceAll('\\n', '\n') || FALLBACK_MESSAGE;

        el.__livewire_confirm = (action, instead) => {
            confirmDialog(message, confirmOptionsFrom(el)).then((accepted) => {
                if (accepted) {
                    action();
                } else {
                    instead();
                }
            });
        };
    });
}
