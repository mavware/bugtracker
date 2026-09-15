---
paths:
  - 'resources/views/**'
---

# Views

## Hiding a Flux button by class needs the app.css override
Flux buttons ship with `inline-flex`, and Tailwind v4 emits `.hidden` earlier in the utilities layer than `.inline-flex`, so `<flux:button class="hidden">` alone loses the cascade and the button stays on screen. This kept End night and Discard night visible on the capture page before any night had started, and the same latent bug sat on the dashboard's "Remove local copies" button. resources/css/app.css now carries an unlayered `[data-flux-button].hidden { display: none }` to settle it — keep that rule, and do not "fix" a stuck button by switching the JS from classList to something else. Scripts (capture.js, claim.js) toggle plain `hidden` on these buttons and rely on it.

## Every "are you sure?" goes through x-confirm-dialog, never a browser-native box
The layouts (app/sidebar, app/header, watch) and welcome.blade.php each render <x-confirm-dialog /> once: a native <dialog data-confirm-dialog> styled like the capture checklist, driven by resources/js/confirmDialog.js. Page scripts call confirmDialog(message, {confirmLabel, destructive, title, detail, cancelLabel}) → Promise<boolean> instead of window.confirm (it falls back to window.confirm only on a page without the dialog). wire:confirm buttons keep the directive; app.js routes them through the dialog at livewire:init via themeLivewireConfirms(), which uses Livewire.hook('directive.init') to replace el.__livewire_confirm (Livewire refuses a second directive('confirm') registration, so the hook is the only seam). A trigger says what it does with data-confirm-label="Delete session" and data-confirm-destructive (shows the red accept button and icon). The message splits at the first "?" followed by a space: the question is the heading, the rest the detail. wire:confirm.prompt is left to Livewire. Any new full-page view with a confirm needs the component. Specs: tests/js/confirmDialog.test.js, confirmDialogLogic.test.js; ConfirmDialogTest pins the layouts.

## Buttons get cursor:pointer from app.css, so do not add cursor-pointer to them
Tailwind v4 dropped the pointer cursor on buttons. resources/css/app.css restores it in @layer base for button:not(:disabled), [role="button"], submit/button/reset inputs and <summary>. A <flux:button>, <flux:menu.item as="button"> or plain <button> therefore needs no cursor-pointer class; keep the class only on things that are not buttons but act like one (a clickable table row, a div). Disabled Flux buttons keep the arrow via disabled:cursor-default, which wins over base.
