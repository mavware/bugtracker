@props(['config'])

{{-- The whole guest watch screen: the panel a night is run from and the table of
     nights this browser is holding. The watch page is this and nothing else, and
     the welcome page leads with it, so both get the same screen from one place —
     two copies would drift the moment either was edited. --}}
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
    mode="local"
>
    {{-- Said once, in the box that stays up all night: what is kept, where it is
         kept, and what removes it. It used to be split between here and the
         setup advice, which disappears as soon as the night begins. --}}
    <x-slot:heroSection>
        <div class="flex flex-col gap-6">
                        <flux:badge color="amber" size="sm" icon="moon" class="w-fit">{{ __('Overnight pest surveillance') }}</flux:badge>

                        <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            {{ __('Find out what walks through the kitchen at 3am.') }}
                        </h1>

                        <p class="max-w-xl text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                            {{ __('Point any phone or laptop camera at the room, leave it running all night, and wake up to every sighting, where each one came in, and whether last week\'s bait station made any difference.') }}
                        </p>

                        <div class="flex flex-wrap items-center gap-3">
                            @auth
                                <flux:button :href="route('dashboard')" variant="primary" icon="play">
                                    {{ __('Start a session') }}
                                </flux:button>
                            @else
                                @if (Route::has('register'))
                                    <flux:button :href="route('register')" variant="primary" icon="play">
                                        {{ __('Create a free account') }}
                                    </flux:button>
                                @endif
                                <flux:button :href="route('login')" variant="ghost" icon-trailing="arrow-right">
                                    {{ __('I already have one') }}
                                </flux:button>
                                <flux:button :href="route('watch.capture')" variant="ghost" icon="eye" data-test="welcome-try-link">
                                    {{ __('Try it tonight, no account') }}
                                </flux:button>
                            @endauth
                        </div>

                        <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <li class="flex items-center gap-1.5">
                                <flux:icon name="device-phone-mobile" variant="micro" />
                                {{ __('No hardware to buy') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <flux:icon name="cpu-chip" variant="micro" />
                                {{ __('Detection runs on the device') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <flux:icon name="lock-closed" variant="micro" />
                                {{ __('Only you can see your footage') }}
                            </li>
                        </ul>
                    </div>
    </x-slot:heroSection>
    <x-slot:privacyNote>
        <flux:heading size="sm">{{ __('Nothing leaves this device') }}</flux:heading>
        <p class="mt-2">
            {{ __('Detection runs entirely in this browser. Bug paths and tiny snapshots stay in this browser on this device, no video is stored, and clearing site data removes them.') }}
        </p>
        @guest
            {{-- data-app-nav so capture.js makes it inert overnight: following any
                 link off this page ends the night. --}}
            <p class="mt-2" data-app-nav>
                <flux:link href="{{ route('register') }}">{{ __('Create a free account') }}</flux:link>
                {{ __('to keep your nights and see trends across them.') }}
            </p>
        @endguest
    </x-slot:privacyNote>
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
