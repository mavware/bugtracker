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
                        {{-- Its own icons and label rather than Flux's icon prop: while the
                             button is disabled capture.js swaps the play for a spinner and
                             writes the status into the label, so the button itself says
                             what the page is doing. --}}
                        <flux:button size="sm" variant="primary" data-capture="start" data-test="start-capture-button">
                            <flux:icon name="play" variant="micro" data-capture="start-play" />
                            <flux:icon.loading variant="micro" class="hidden" data-capture="start-spinner" />
                            <span data-capture="start-label">{{ __('Start tracking') }}</span>
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

    {{-- The room checklist, asked before the camera opens: the light has to be on
         before calibration measures the scene, and someone who backs out should
         not have been filmed. A native dialog rather than window.confirm so it can
         be laid out, and rather than a Flux modal so capture.js can open and await
         it with no Alpine in between. Start and Cancel close it with a return
         value capture.js reads; Escape closes it empty, which counts as Cancel. --}}
    <dialog
        data-capture="preflight"
        data-test="preflight-dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-2xl shadow-zinc-900/20 backdrop:bg-zinc-950/60 backdrop:backdrop-blur-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:shadow-black/60"
    >
        <div class="p-6 sm:p-8">
            <div class="flex items-start gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                    <flux:icon name="moon" class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">{{ __('Before you start, check the room') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('The camera watches for anything that changes between frames, so the room has to be lit, and still.') }}</flux:text>
                </div>
            </div>

            <ol class="mt-6 space-y-3">
                <li class="flex items-start gap-3 rounded-xl border border-zinc-200 p-3.5 dark:border-zinc-700">
                    <flux:icon name="light-bulb" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <p class="text-sm font-medium">{{ __('Turn on a light') }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('A dim lamp or nightlight is enough, but the camera cannot see in pitch darkness.') }}</p>
                    </div>
                </li>
                <li class="flex items-start gap-3 rounded-xl border border-zinc-200 p-3.5 dark:border-zinc-700">
                    <flux:icon name="arrow-path" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <p class="text-sm font-medium">{{ __('Turn off fans, heaters, and anything else that moves') }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('A stirring curtain or a spinning blade is a sighting every few seconds.') }}</p>
                    </div>
                </li>
                <li class="flex items-start gap-3 rounded-xl border border-zinc-200 p-3.5 dark:border-zinc-700">
                    <flux:icon name="tv" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <p class="text-sm font-medium">{{ __('Turn off televisions and screens, and cover blinking LEDs') }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Changing colour reads as movement.') }}</p>
                    </div>
                </li>
                <li class="flex items-start gap-3 rounded-xl border border-zinc-200 p-3.5 dark:border-zinc-700">
                    <flux:icon name="window" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <p class="text-sm font-medium">{{ __('Draw the curtains if you can') }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('So passing headlights do not sweep the room.') }}</p>
                    </div>
                </li>
            </ol>

            <div class="mt-6 flex items-center gap-2 rounded-xl bg-zinc-50 p-3.5 text-sm text-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-300">
                <flux:icon name="clock" variant="mini" class="shrink-0 text-zinc-400" />
                {{ __('Then leave the room. You have five seconds once you press Start.') }}
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 px-6 py-4 sm:px-8 dark:border-zinc-700">
            <flux:button variant="filled" data-capture="preflight-cancel" data-test="preflight-cancel-button">
                {{ __('Not yet') }}
            </flux:button>
            <flux:button variant="primary" icon="play" data-capture="preflight-start" data-test="preflight-start-button">
                {{ __('Start tracking') }}
            </flux:button>
        </div>
    </dialog>

    @vite('resources/js/surveillance/capture.js')
</section>
