@props([
    'config',
    'name' => null,
    'intro' => null,
    'mode' => 'server',
    'asideFirst' => false,
])

{{-- The capture page proper, shared by the logged-in page, the guest one and the
     welcome hero. capture.js reads data-config from #capture-app and drives every
     data-capture element below; the surrounding page only chooses the copy.

     The side column has two faces. setupHelp is for an idle page and goes the
     moment a night starts; nightHelp is hidden until then and holds what is
     worth reading with the room dark. asideFirst puts the column before the
     camera, which is how a hero reads: copy, then the thing it is about. --}}
<section class="w-full" id="capture-app" data-config="{{ json_encode($config) }}">
    @if ($name !== null)
        <div>
            <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                {{ $name }}
            </h1>
            <flux:text class="mt-2">{{ $intro ?? '' }}</flux:text>
        </div>
    @endif

    <div data-capture="banner" class="mt-4 hidden rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-600 dark:text-amber-400"></div>

    <div @class(['grid gap-6 lg:grid-cols-3', 'mt-6' => $name !== null, 'mt-4' => $name === null])>
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
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
                        <span class="hidden text-sm text-zinc-500 tabular-nums dark:text-zinc-400" data-capture="elapsed"></span>
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
                    {{-- A source-less <video> collapses to a short black strip, so the
                         sample room stands in at the shape a camera frame will have and
                         says what a finished night looks like. capture.js swaps the two
                         over around every camera.start()/stop(): the live picture has to
                         be the element setting this box's height, because the overlay
                         canvas is sized from the video's own client box. --}}
                    <div data-capture="placeholder" class="relative aspect-2/1">
                        <x-surveillance.sample-room aria-hidden="true" class="absolute inset-0" />

                        <div class="absolute bottom-3 left-3 rounded-md bg-black/60 px-2 py-1 text-xs text-zinc-300 backdrop-blur">
                            {{ __('A sample night. Your camera appears here once you start.') }}
                        </div>
                    </div>

                    <video data-capture="video" class="hidden w-full" autoplay muted playsinline></video>
                    <canvas data-capture="overlay" class="absolute inset-0 h-full w-full"></canvas>
                </div>

                {{-- The night's numbers, in a footer under the camera they were read
                     off. State and elapsed stay in the header above it: two copies of
                     either would need two elements for capture.js to write to.
                     Every cell carries the same right and bottom hairline and the row
                     hangs a pixel past the card, so the card's overflow-hidden clips
                     the last column and row. divide-x cannot do this: it would draw a
                     stray line down the first column once two columns wrap to four. --}}
                <dl class="-mr-px -mb-px grid grid-cols-2 border-t border-zinc-200 sm:grid-cols-4 dark:border-zinc-700">
                    <div class="border-r border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ __('Bugs tracked') }}</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums sm:text-2xl" data-capture="track-count">0</dd>
                    </div>
                    <div class="border-r border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ __('Live tracks') }}</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums sm:text-2xl" data-capture="live-count">0</dd>
                    </div>
                    <div class="border-r border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ $mode === 'local' ? __('Saving') : __('Upload queue') }}</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums sm:text-2xl" data-capture="queue-depth">0</dd>
                    </div>
                    <div class="border-r border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ __('Scene brightness') }}</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums sm:text-2xl" data-capture="brightness">–</dd>
                    </div>
                </dl>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm text-zinc-500">
                <input type="checkbox" data-capture="debug-toggle" class="rounded" checked />
                {{ __('Show detection boxes') }}
            </label>
        </div>

        <div @class(['space-y-4', 'order-first' => $asideFirst])>
            {{-- Setup advice, or a hero's copy: idle-page reading, hidden once
                 watching starts. --}}
            <div data-capture="setup-help" class="space-y-4">
                {{ $setupHelp ?? '' }}
            </div>

            {{-- What takes its place for the night. The screen-sleep advice lives
                 here rather than in the setup help because a slept screen is the
                 one thing that silently ends the night, so it has to be on screen
                 while the night is running, not only while it is being set up. --}}
            <div data-capture="night-help" class="hidden space-y-4">
                {{ $nightHelp ?? '' }}

                <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Keep it running all night') }}</flux:heading>
                    <p class="mt-2">
                        {{ __('Leave this device plugged in, the screen on, and this tab in front. Opening anything else on it ends the night.') }}
                    </p>

                    <flux:heading size="sm" class="mt-4">{{ __('If the screen keeps sleeping') }}</flux:heading>
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

            {{-- The claim that has to hold all night, so it sits outside both faces
                 above. A page can replace it to fold its own note about the night's
                 storage into the same box. --}}
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
