@props([
    'config',
    'name',
    'intro',
    'mode' => 'server',
])

{{-- The capture page proper, shared by the logged-in page and the guest one.
     capture.js reads data-config from #capture-app and drives every data-capture
     element below; the surrounding page only chooses the copy and the setup help. --}}
<section class="w-full" id="capture-app" data-config="{{ json_encode($config) }}">
    <div>
        <flux:heading size="xl">{{ $name }}</flux:heading>
        <flux:text class="mt-2">{{ $intro }}</flux:text>
    </div>

    <div data-capture="banner" class="mt-4 hidden rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-600 dark:text-amber-400"></div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                {{-- The night's own header, sitting on the camera it is watching: what
                     the capture is doing on the left, and the controls for it on the
                     right. capture.js swaps the light and the buttons over at the one
                     moment the night actually begins. --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <span class="relative flex size-2.5">
                            <span data-capture="idle-light" class="absolute inset-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                            <span data-capture="live-light" class="hidden">
                                <span class="absolute inset-0 animate-ping rounded-full bg-green-400 opacity-75"></span>
                                <span class="absolute inset-0 rounded-full bg-green-500"></span>
                            </span>
                        </span>
                        <span class="text-sm font-medium" data-capture="state">{{ __('Idle') }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span data-capture="started" class="hidden text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Started') }} <span data-capture="started-at" class="tabular-nums"></span>
                        </span>

                        <flux:button size="sm" variant="subtle" icon="camera" data-capture="check" data-test="check-camera-button">
                            <span data-capture="check-label">{{ __('Check camera') }}</span>
                        </flux:button>
                        <flux:button size="sm" variant="primary" icon="play" data-capture="start" data-test="start-capture-button">
                            {{ __('Start watching') }}
                        </flux:button>
                        <flux:button size="sm" variant="danger" icon="stop" data-capture="end" data-test="end-session-button" class="hidden">
                            {{ __('End night') }}
                        </flux:button>
                    </div>
                </div>

                <div class="relative bg-black">
                    <video data-capture="video" class="w-full" autoplay muted playsinline></video>
                    <canvas data-capture="overlay" class="absolute inset-0 h-full w-full"></canvas>
                </div>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm text-zinc-500">
                <input type="checkbox" data-capture="debug-toggle" class="rounded" checked />
                {{ __('Show detection boxes') }}
            </label>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Status') }}</flux:heading>
                {{-- State lives in the header above the camera, not here: two copies of
                     it would need two elements for capture.js to write to. --}}
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Elapsed') }}</dt>
                        <dd data-capture="elapsed">–</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Bugs tracked') }}</dt>
                        <dd data-capture="track-count">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Live tracks') }}</dt>
                        <dd data-capture="live-count">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ $mode === 'local' ? __('Saving') : __('Upload queue') }}</dt>
                        <dd data-capture="queue-depth">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Scene brightness') }}</dt>
                        <dd data-capture="brightness">–</dd>
                    </div>
                </dl>
            </div>

            {{-- Setup advice, not night-time reading: hidden once watching starts. --}}
            <div data-capture="setup-help" class="space-y-4">
                {{ $setupHelp ?? '' }}

                <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('If the screen keeps sleeping') }}</flux:heading>
                    <p class="mt-2">
                        {{ __('This page asks the device to stay awake on its own, so usually there is nothing to do. If it sleeps anyway, the camera stops and the rest of the night is lost — set it manually once:') }}
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        <li>{{ __('iPhone or iPad: Auto-Lock to Never, and Low Power Mode off — it blocks the screen lock on its own.') }}</li>
                        <li>{{ __('Android: Screen timeout to its longest option.') }}</li>
                        <li>{{ __('Laptop: turn off screen sleep in your system power settings.') }}</li>
                        <li>{{ __('Keep it plugged in either way — a screen held on all night will flatten a battery.') }}</li>
                    </ul>
                </div>
            </div>

            {{-- The claim that has to hold all night, so it sits outside setup-help,
                 which is hidden the moment watching starts. A page can replace it to
                 fold its own note about the night's storage into the same box. --}}
            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                @if (isset($privacyNote))
                    {{ $privacyNote }}
                @elseif ($mode === 'local')
                    {{ __('Detection runs entirely in this browser. Nothing leaves this device — bug paths and tiny snapshots are kept here, and no video is stored.') }}
                @else
                    {{ __('Detection runs entirely in this browser. Only bug paths and tiny snapshots are uploaded — no video is stored.') }}
                @endif
            </div>
        </div>
    </div>

    @vite('resources/js/surveillance/capture.js')
</section>
