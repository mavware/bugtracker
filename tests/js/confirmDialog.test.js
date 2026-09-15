// @vitest-environment happy-dom

// The shared confirm dialog, driven the way a person and Livewire drive it:
// the layout's markup is mounted, a question is asked, and the answer follows
// from which button closes it.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { confirmDialog, themeLivewireConfirms } from '../../resources/js/confirmDialog.js';

const dialog = () => document.querySelector('dialog[data-confirm-dialog]');
const part = (name) => document.querySelector(`[data-confirm="${name}"]`);
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

function mountDialog() {
    document.body.innerHTML = `
        <dialog data-confirm-dialog>
            <span data-confirm="icon"></span>
            <h2 data-confirm="title">Are you sure?</h2>
            <p data-confirm="detail" class="hidden"></p>
            <button data-confirm="cancel"><span data-confirm="cancel-label">Cancel</span></button>
            <button data-confirm="accept"><span data-confirm="accept-label">Confirm</span></button>
            <button data-confirm="accept-danger" class="hidden"><span data-confirm="accept-danger-label">Confirm</span></button>
        </dialog>
    `;
}

describe('confirmDialog', () => {
    beforeEach(() => {
        window.confirm = vi.fn(() => true);
        mountDialog();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('opens with the question as its heading and the consequence beneath, and accepts', async () => {
        const answer = confirmDialog('Remove this customer? Their recorded nights are kept.');

        expect(dialog().open).toBe(true);
        expect(part('title').textContent).toBe('Remove this customer?');
        expect(part('detail').textContent).toBe('Their recorded nights are kept.');
        expect(part('detail').classList.contains('hidden')).toBe(false);
        expect(part('accept').classList.contains('hidden')).toBe(false);
        expect(part('accept-danger').classList.contains('hidden')).toBe(true);
        expect(part('accept-label').textContent).toBe('Confirm');
        expect(part('cancel-label').textContent).toBe('Cancel');

        part('accept').click();

        await expect(answer).resolves.toBe(true);
        expect(dialog().open).toBe(false);
        expect(window.confirm).not.toHaveBeenCalled();
    });

    test('a lone question hides the detail line', async () => {
        const answer = confirmDialog('Remove this intervention?');

        expect(part('detail').classList.contains('hidden')).toBe(true);

        part('accept').click();
        await answer;
    });

    test('a destructive question shows the danger button with its own label and tints the icon', async () => {
        const answer = confirmDialog('Delete this night?', { confirmLabel: 'Delete night', destructive: true });

        expect(part('accept').classList.contains('hidden')).toBe(true);
        expect(part('accept-danger').classList.contains('hidden')).toBe(false);
        expect(part('accept-danger-label').textContent).toBe('Delete night');
        expect(part('icon').classList.contains('is-destructive')).toBe(true);

        part('accept-danger').click();

        await expect(answer).resolves.toBe(true);

        // The next plain question puts the primary button back.
        const next = confirmDialog('Close out?');
        expect(part('accept').classList.contains('hidden')).toBe(false);
        expect(part('accept-danger').classList.contains('hidden')).toBe(true);
        expect(part('icon').classList.contains('is-destructive')).toBe(false);
        part('cancel').click();
        await next;
    });

    test('explicit title, detail and cancel label win over the split message', async () => {
        const answer = confirmDialog('ignored', { title: 'Stop here?', detail: 'Nothing is lost.', cancelLabel: 'Keep going' });

        expect(part('title').textContent).toBe('Stop here?');
        expect(part('detail').textContent).toBe('Nothing is lost.');
        expect(part('cancel-label').textContent).toBe('Keep going');

        part('cancel').click();
        await answer;
    });

    test('Cancel answers no', async () => {
        const answer = confirmDialog('Delete this?');

        part('cancel').click();

        await expect(answer).resolves.toBe(false);
    });

    // Escape is how a keyboard closes a dialog, and it hands back no value.
    test('closing the dialog empty answers no', async () => {
        const answer = confirmDialog('Delete this?');

        dialog().close('');

        await expect(answer).resolves.toBe(false);
    });

    test('a click on the backdrop answers no, a click inside the panel does not', async () => {
        const answer = confirmDialog('Delete this?');

        part('title').dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(dialog().open).toBe(true);

        dialog().dispatchEvent(new MouseEvent('click', { bubbles: true }));

        await expect(answer).resolves.toBe(false);
    });

    test('a question asked while one is open is answered no without throwing', async () => {
        const first = confirmDialog('First?');

        await expect(confirmDialog('Second?')).resolves.toBe(false);
        expect(part('title').textContent).toBe('First?');

        part('accept').click();
        await expect(first).resolves.toBe(true);
    });

    test('the buttons stop listening once the dialog closes', async () => {
        const first = confirmDialog('First?');
        part('cancel').click();
        await first;

        const second = confirmDialog('Second?');
        part('accept').click();

        await expect(second).resolves.toBe(true);
    });

    test('falls back to the browser box on a page without the dialog', async () => {
        document.body.innerHTML = '';
        window.confirm = vi.fn(() => false);

        await expect(confirmDialog('Delete this?')).resolves.toBe(false);
        expect(window.confirm).toHaveBeenCalledWith('Delete this?');
    });
});

describe('themeLivewireConfirms', () => {
    let hooks;
    let el;

    // Stands in for Livewire's hook registry: the callback is what Livewire
    // would call for every wire:* directive as a component initialises.
    const livewire = { hook: (name, callback) => hooks[name].push(callback) };
    const initDirective = (directive) => hooks['directive.init'].forEach((callback) => callback({ el, directive }));
    const confirmDirective = (expression, modifiers = []) => ({ value: 'confirm', modifiers, expression });

    beforeEach(() => {
        hooks = { 'directive.init': [] };
        window.confirm = vi.fn(() => true);
        mountDialog();
        el = document.createElement('button');
        document.body.appendChild(el);
        themeLivewireConfirms(livewire);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('replaces the confirm hook with one that asks the dialog and runs the action on accept', async () => {
        initDirective(confirmDirective('Delete this session? Its images go with it.'));
        const action = vi.fn();
        const instead = vi.fn();

        el.__livewire_confirm(action, instead);

        expect(dialog().open).toBe(true);
        expect(part('title').textContent).toBe('Delete this session?');
        expect(part('detail').textContent).toBe('Its images go with it.');
        expect(window.confirm).not.toHaveBeenCalled();

        part('accept').click();
        await settle();

        expect(action).toHaveBeenCalledTimes(1);
        expect(instead).not.toHaveBeenCalled();
    });

    test('runs the instead branch on cancel', async () => {
        initDirective(confirmDirective('Delete this?'));
        const action = vi.fn();
        const instead = vi.fn();

        el.__livewire_confirm(action, instead);
        part('cancel').click();
        await settle();

        expect(action).not.toHaveBeenCalled();
        expect(instead).toHaveBeenCalledTimes(1);
    });

    test('takes the label and tone from the trigger, and the plain question from an empty message', async () => {
        el.dataset.confirmLabel = 'Delete account';
        el.setAttribute('data-confirm-destructive', '');
        initDirective(confirmDirective(''));

        el.__livewire_confirm(vi.fn(), vi.fn());

        expect(part('title').textContent).toBe('Are you sure?');
        expect(part('accept-danger').classList.contains('hidden')).toBe(false);
        expect(part('accept-danger-label').textContent).toBe('Delete account');

        part('accept-danger').click();
        await settle();
    });

    test('leaves other directives and wire:confirm.prompt to Livewire', () => {
        initDirective({ value: 'click', modifiers: [], expression: 'save' });
        expect(el.__livewire_confirm).toBeUndefined();

        initDirective(confirmDirective('Type DELETE|DELETE', ['prompt']));
        expect(el.__livewire_confirm).toBeUndefined();
    });
});
