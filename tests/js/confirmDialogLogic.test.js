import { describe, expect, test } from 'vitest';
import { confirmOptionsFrom, FALLBACK_MESSAGE, splitConfirmMessage } from '../../resources/js/confirmDialogLogic.js';

describe('splitConfirmMessage', () => {
    test('leads with the question and explains the consequence underneath', () => {
        expect(splitConfirmMessage('Remove this customer? Their recorded nights are kept, but no longer grouped.')).toEqual({
            title: 'Remove this customer?',
            detail: 'Their recorded nights are kept, but no longer grouped.',
        });
    });

    test('a lone question is all heading', () => {
        expect(splitConfirmMessage('Remove this intervention?')).toEqual({ title: 'Remove this intervention?', detail: '' });
    });

    test('a message with no question mark breaks at its first line break instead', () => {
        expect(splitConfirmMessage('This ends the night.\nNothing more is recorded.')).toEqual({
            title: 'This ends the night.',
            detail: 'Nothing more is recorded.',
        });
        expect(splitConfirmMessage('Escaped\\nbreaks count too.')).toEqual({ title: 'Escaped', detail: 'breaks count too.' });
    });

    test('a question mark inside the heading only splits at the first one followed by a break', () => {
        expect(splitConfirmMessage('Delete "why?" and its notes? They cannot be recovered.')).toEqual({
            title: 'Delete "why?" and its notes?',
            detail: 'They cannot be recovered.',
        });
    });

    test('an empty message asks the plain question', () => {
        expect(splitConfirmMessage('')).toEqual({ title: FALLBACK_MESSAGE, detail: '' });
        expect(splitConfirmMessage(undefined)).toEqual({ title: FALLBACK_MESSAGE, detail: '' });
    });
});

describe('confirmOptionsFrom', () => {
    test('reads the label and tone a trigger carries as data attributes', () => {
        expect(confirmOptionsFrom({ dataset: { confirmLabel: 'Delete session', confirmDestructive: '' } })).toEqual({
            confirmLabel: 'Delete session',
            destructive: true,
        });
    });

    test('a bare trigger leaves both to the dialog defaults', () => {
        expect(confirmOptionsFrom({ dataset: {} })).toEqual({ confirmLabel: undefined, destructive: false });
        expect(confirmOptionsFrom({ dataset: { confirmLabel: '' } })).toEqual({ confirmLabel: undefined, destructive: false });
    });
});
