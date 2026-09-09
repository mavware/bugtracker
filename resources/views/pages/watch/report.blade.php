<x-layouts::watch :title="__('Report')">
    {{-- A shell: localReport.js reads the night out of this browser's store and
         fills every data-report element. It mirrors the logged-in report page so
         the two read the same. --}}
    <div id="report-app" data-mode="local" data-local-id="{{ $localId }}" data-config="{{ json_encode($config) }}">
        <flux:callout icon="question-mark-circle" class="hidden" data-report="missing" data-test="missing-night-notice">
            <flux:callout.heading>{{ __('This night is not on this device') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Nights are kept only in the browser that recorded them, so this one may have been recorded elsewhere, or removed.') }}
                <flux:link href="{{ route('watch.capture') }}">{{ __('Back to watching') }}</flux:link>
            </flux:callout.text>
        </flux:callout>

        <div data-report="volatile" class="mb-6 hidden rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-600 dark:text-amber-400"></div>

        <div data-report="page" class="hidden">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl" data-report="title"></flux:heading>
                    <flux:text class="mt-2" data-report="range"></flux:text>
                </div>
                <flux:button href="{{ route('watch.capture') }}" icon="arrow-left">{{ __('Back') }}</flux:button>
            </div>

            <flux:callout icon="x-circle" class="mt-6 hidden" data-report="discarded-notice" data-test="discarded-notice">
                <flux:callout.heading>{{ __('You discarded this night') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Everything it caught is still here.') }}</flux:callout.text>
            </flux:callout>

            <flux:callout icon="cloud-arrow-up" class="mt-6" data-report="claim-panel">
                <flux:callout.heading>
                    @auth
                        {{ __('Keep this night') }}
                    @else
                        {{ __('This night lives only on this device') }}
                    @endauth
                </flux:callout.heading>
                <flux:callout.text>
                    @auth
                        {{ __('Save it to your account so it counts towards trends and entry points, and survives clearing this browser.') }}
                    @else
                        {{ __('Create a free account to keep nights and see trends across them.') }}
                        <flux:link href="{{ route('register') }}">{{ __('Create an account') }}</flux:link>
                    @endauth
                </flux:callout.text>
                @auth
                    <x-slot:actions>
                        <flux:input size="sm" placeholder="{{ __('Room (optional)') }}" data-report="room" class="max-w-48" />
                        <flux:button size="sm" variant="primary" data-report="claim" data-test="claim-night-button">{{ __('Save to my account') }}</flux:button>
                    </x-slot:actions>
                @endauth
            </flux:callout>

            <flux:callout icon="check-circle" class="mt-6 hidden" data-report="claimed-notice" data-test="claimed-notice">
                <flux:callout.heading>{{ __('Saved to your account') }}</flux:callout.heading>
                <flux:callout.text>
                    <flux:link data-report="claimed-link" href="#">{{ __('Open the report in your account') }}</flux:link>
                </flux:callout.text>
            </flux:callout>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('Bug sightings') }}</flux:text>
                    <flux:heading size="xl" data-report="stat-track-count">0</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('Top entry point') }}</flux:text>
                    <flux:heading size="xl" data-report="stat-entry">{{ __('None') }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('Top exit point') }}</flux:text>
                    <flux:heading size="xl" data-report="stat-exit">{{ __('None') }}</flux:heading>
                </div>
            </div>

            <div class="mt-6">
                <x-surveillance.replay-controls />
            </div>

            <div data-report="sightings" class="hidden">
                <flux:heading size="lg" class="mt-8">{{ __('Sightings') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Snapshots let you verify each sighting was really a bug. Click a row to highlight its trail, or mark false positives to exclude them from the report.') }}</flux:text>

                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Time') }}</flux:table.column>
                        <flux:table.column>{{ __('Duration') }}</flux:table.column>
                        <flux:table.column>{{ __('Entered') }}</flux:table.column>
                        <flux:table.column>{{ __('Exited') }}</flux:table.column>
                        <flux:table.column>{{ __('First seen') }}</flux:table.column>
                        <flux:table.column>{{ __('Last seen') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows data-report="rows"></flux:table.rows>
                </flux:table>
            </div>

            <flux:callout class="mt-8 hidden" data-report="no-sightings">
                <flux:callout.heading>{{ __('No bugs detected') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Nothing moved through the frame this night — or the room was too dark to see it.') }}</flux:callout.text>
            </flux:callout>

            <div class="mt-8">
                <flux:button variant="subtle" icon="trash" data-report="delete" data-test="delete-night-button">{{ __('Delete this night from this device') }}</flux:button>
            </div>
        </div>

        <template data-report="row-template">
            <flux:table.row data-track-id="" class="cursor-pointer">
                <flux:table.cell variant="strong" data-cell="time"></flux:table.cell>
                <flux:table.cell data-cell="duration"></flux:table.cell>
                <flux:table.cell data-cell="entered"></flux:table.cell>
                <flux:table.cell data-cell="exited"></flux:table.cell>
                <flux:table.cell data-cell="start-crop"></flux:table.cell>
                <flux:table.cell data-cell="end-crop"></flux:table.cell>
                <flux:table.cell>
                    <flux:button size="sm" variant="subtle" data-report="toggle" data-test="toggle-dismissed-button">{{ __('Not a bug') }}</flux:button>
                </flux:table.cell>
            </flux:table.row>
        </template>
    </div>

    @vite('resources/js/surveillance/localReport.js')
</x-layouts::watch>
