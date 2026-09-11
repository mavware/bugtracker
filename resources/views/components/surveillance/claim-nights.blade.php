{{-- Nights this browser recorded before the user had an account, one row each
     so every night can be filed under its own room and customer as it is saved.
     Self-contained: the markup claim.js reads, the config it needs, and the
     script itself, so a page shows the panel with one tag. Saving removes the
     local copy, so a row that reads "saved" is one claimed from its own report
     page, which keeps the copy it is showing. claim.js fills the list from the
     night store and hides the whole panel while the store holds nothing. The
     customers go in the config so the script can offer them per row. --}}
<div
    id="claim-nights"
    class="hidden"
    data-config="{{ json_encode([
        'csrfToken' => csrf_token(),
        'routes' => ['import' => route('surveillance.import')],
        'customers' => auth()->user()->customers()->orderBy('name')->get(['id', 'name'])->map(fn ($customer) => ['id' => $customer->id, 'name' => $customer->name])->all(),
    ]) }}"
>
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="claim-nights-panel">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon name="cloud-arrow-up" variant="mini" class="text-zinc-400" />
                    {{ __('Unsaved Sessions') }}
                </flux:heading>
                <flux:text class="mt-1 text-sm">
                    <span data-claim="pending">
                        {{ __('This browser holds') }} <span data-claim="count">0</span> {{ __('sessions recorded without an account. Save each one here, under its room and customer, so it counts towards trends and entry points.') }}
                    </span>
                    <span data-claim="all-saved" class="hidden">{{ __('Every night this browser holds is saved to your account.') }}</span>
                </flux:text>
            </div>
        </div>

        <p data-claim="progress" class="mt-3 hidden text-sm text-amber-600 dark:text-amber-400"></p>

        {{-- Capped and scrolling: a browser can hold a season of nights, and
             the sessions list below is the point of the page. --}}
        <div class="mt-3 max-h-80 overflow-y-auto">
            <ul data-claim="rows" class="divide-y divide-zinc-200 dark:divide-zinc-700"></ul>
        </div>

        <template data-claim="row-template">
            <li data-night-id="" class="flex flex-wrap items-center gap-3 py-3">
                <div class="min-w-48 flex-1">
                    <p class="font-medium" data-cell="name"></p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        <span data-cell="started"></span>
                        <span class="text-zinc-400">&middot;</span>
                        <span data-cell="sightings"></span> {{ __('sightings') }}
                        <span class="text-zinc-400">&middot;</span>
                        <span data-cell="status"></span>
                    </p>
                </div>

                <div data-cell="controls" class="flex flex-wrap items-center gap-2">
                    <select data-cell="customer" class="hidden h-8 rounded-lg border border-zinc-300 bg-white px-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">{{ __('No customer') }}</option>
                    </select>
                    <input data-cell="room" type="text" maxlength="80" placeholder="{{ __('Room (optional)') }}" class="h-8 w-40 rounded-lg border border-zinc-300 bg-white px-2 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
                    <flux:button size="sm" variant="primary" icon="cloud-arrow-up" data-cell="import">
                        <span data-cell="import-label">{{ __('Import') }}</span>
                    </flux:button>
                </div>

                <div data-cell="saved" class="hidden">
                    <div class="flex items-center gap-2">
                        <span class="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                            <flux:icon name="check-circle" variant="micro" />
                            {{ __('Saved to account') }}
                        </span>
                        <flux:button size="sm" variant="subtle" icon="trash" data-cell="remove">{{ __('Remove local copy') }}</flux:button>
                    </div>
                </div>
            </li>
        </template>
    </div>
</div>

@vite('resources/js/surveillance/claim.js')
