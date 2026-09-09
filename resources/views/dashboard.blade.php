<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
{{--        <div class="grid auto-rows-min gap-4 md:grid-cols-3">--}}
{{--            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">--}}
{{--                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />--}}
{{--            </div>--}}
{{--            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">--}}
{{--                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />--}}
{{--            </div>--}}
{{--            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">--}}
{{--                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />--}}
{{--            </div>--}}
{{--        </div>--}}
        <livewire:surveillance.tonight />

        {{-- Nights this browser recorded before the user had an account. claim.js
             shows the panel only when the night store holds some. --}}
        <div
            id="claim-nights"
            class="hidden"
            data-config="{{ json_encode(['csrfToken' => csrf_token(), 'routes' => ['import' => route('surveillance.import')]]) }}"
        >
            <flux:callout icon="cloud-arrow-up" data-test="claim-nights-panel">
                <flux:callout.heading>{{ __('Nights on this device') }}</flux:callout.heading>
                <flux:callout.text>
                    <span data-claim="pending">
                        {{ __('This browser holds') }} <span data-claim="count">0</span> {{ __('nights recorded without an account. Save them here so they count towards trends and entry points.') }}
                    </span>
                    <span data-claim="progress" class="hidden"></span>
                </flux:callout.text>
                <x-slot:actions>
                    <flux:input size="sm" placeholder="{{ __('Room (optional)') }}" data-claim="room" class="max-w-48" />
                    <flux:button size="sm" variant="primary" data-claim="import">{{ __('Import') }}</flux:button>
                    <flux:button size="sm" variant="subtle" data-claim="remove" class="hidden">{{ __('Remove local copies') }}</flux:button>
                </x-slot:actions>
            </flux:callout>
        </div>

        <div class="h-full flex-1 overflow-auto rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <livewire:surveillance.sessions />
        </div>
    </div>

    @vite('resources/js/surveillance/claim.js')
</x-layouts::app>
