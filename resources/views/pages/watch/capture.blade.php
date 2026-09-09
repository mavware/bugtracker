<x-layouts::watch :title="__('Watch a room')">
    @auth
        <flux:callout icon="information-circle" class="mb-6" data-test="watch-signed-in-notice">
            <flux:callout.text>
                {{ __('You are signed in, but nights started here are saved on this device only. To record straight to your account, start from your dashboard.') }}
                <flux:link href="{{ route('dashboard') }}">{{ __('Go to the dashboard') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endauth

    <x-surveillance.capture-panel
        :config="$config"
        :name="__('Watch a room tonight')"
        :intro="__('Keep the device plugged in, the screen on, and this tab visible all night.')"
        mode="local"
    >
        <x-slot:setupHelp>
            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Nothing leaves this device') }}</flux:heading>
                <p class="mt-2">
                    {{ __('Nights recorded here stay in this browser on this device. Clearing site data removes them. Create an account to keep them and see trends across nights.') }}
                </p>
                @guest
                    <p class="mt-2">
                        <flux:link href="{{ route('register') }}">{{ __('Create a free account') }}</flux:link>
                    </p>
                @endguest
            </div>
        </x-slot:setupHelp>
    </x-surveillance.capture-panel>

    {{-- The nights this browser holds. localNights.js fills the table from the
         night store and hides the whole section while it is empty. --}}
    <section id="local-nights" data-config="{{ json_encode($config) }}" class="mt-10 hidden">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Nights on this device') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Only this browser can see these. Clearing site data deletes them.') }}</flux:text>
            </div>

            @auth
                <div class="flex flex-wrap items-center gap-2">
                    <flux:input size="sm" placeholder="{{ __('Room (optional)') }}" data-nights="room" class="max-w-48" />
                    <flux:button size="sm" variant="primary" icon="cloud-arrow-up" data-nights="claim-all">
                        {{ __('Import all to my account') }}
                    </flux:button>
                </div>
            @endauth
        </div>

        <div data-nights="banner" class="mt-4 hidden rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-600 dark:text-amber-400"></div>
        <p data-nights="progress" class="mt-4 hidden text-sm text-zinc-500"></p>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Night') }}</flux:table.column>
                <flux:table.column>{{ __('Started') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Sightings') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows data-nights="rows"></flux:table.rows>
        </flux:table>

        <template data-nights="row-template">
            <flux:table.row data-night-id="">
                <flux:table.cell variant="strong" data-cell="name"></flux:table.cell>
                <flux:table.cell data-cell="started"></flux:table.cell>
                <flux:table.cell data-cell="status"></flux:table.cell>
                <flux:table.cell data-cell="sightings"></flux:table.cell>
                <flux:table.cell>
                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:button size="sm" icon="document-text" data-cell="report">{{ __('Report') }}</flux:button>
                        @auth
                            <flux:button size="sm" variant="subtle" icon="cloud-arrow-up" data-cell="claim">{{ __('Save to account') }}</flux:button>
                        @endauth
                        <flux:button size="sm" variant="subtle" icon="trash" data-cell="remove">{{ __('Remove') }}</flux:button>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        </template>
    </section>

    @vite('resources/js/surveillance/localNights.js')
</x-layouts::watch>
