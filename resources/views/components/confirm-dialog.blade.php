{{-- The app's one "are you sure?" box, rendered once a layout and driven by
     resources/js/confirmDialog.js: page scripts ask it in place of
     window.confirm, and wire:confirm buttons are routed through it from app.js.
     A native dialog, styled like the capture checklist, rather than a Flux
     modal so a script can open and await it with no Alpine in between. Escape
     and a click on the backdrop close it empty, which counts as Cancel. The two
     accept buttons are one control in two tones; the script shows the danger
     one for a destructive question and the primary one otherwise. --}}
<dialog
    data-confirm-dialog
    data-test="confirm-dialog"
    aria-labelledby="confirm-dialog-title"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-2xl shadow-zinc-900/20 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:shadow-black/60"
>
    <div class="flex items-start gap-4 p-6 sm:p-8">
        <span
            data-confirm="icon"
            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 [&.is-destructive]:bg-red-100 [&.is-destructive]:text-red-700 dark:bg-amber-500/15 dark:text-amber-300 dark:[&.is-destructive]:bg-red-500/15 dark:[&.is-destructive]:text-red-300"
        >
            <flux:icon name="exclamation-triangle" class="size-6" />
        </span>
        <div class="min-w-0 flex-1">
            <flux:heading size="lg" id="confirm-dialog-title" data-confirm="title">{{ __('Are you sure?') }}</flux:heading>
            <flux:text class="mt-1 hidden" data-confirm="detail"></flux:text>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 px-6 py-4 sm:px-8 dark:border-zinc-700">
        <flux:button variant="filled" data-confirm="cancel" data-test="confirm-dialog-cancel">
            <span data-confirm="cancel-label">{{ __('Cancel') }}</span>
        </flux:button>
        <flux:button variant="primary" data-confirm="accept" data-test="confirm-dialog-accept">
            <span data-confirm="accept-label">{{ __('Confirm') }}</span>
        </flux:button>
        <flux:button variant="danger" class="hidden" data-confirm="accept-danger" data-test="confirm-dialog-accept-danger">
            <span data-confirm="accept-danger-label">{{ __('Confirm') }}</span>
        </flux:button>
    </div>
</dialog>
