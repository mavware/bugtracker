<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Welcome')])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="relative isolate overflow-hidden">
            {{-- Night-sky backdrop: a soft amber glow near the top that fades into the page, over a faint grid. --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[40rem] bg-radial-[ellipse_at_top] from-amber-100/70 via-white to-white dark:from-amber-500/10 dark:via-zinc-950 dark:to-zinc-950"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(to_right,rgb(0_0_0/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(0_0_0/0.04)_1px,transparent_1px)] bg-[size:3rem_3rem] [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_70%)] dark:bg-[linear-gradient(to_right,rgb(255_255_255/0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.05)_1px,transparent_1px)]"></div>

            <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                        <x-app-logo-icon class="size-5 fill-current" />
                    </span>
                    <span>{{ config('app.name', 'BugTracker') }}</span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-2" aria-label="{{ __('Account') }}">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" icon-trailing="arrow-right" data-test="welcome-dashboard-link">
                                {{ __('Dashboard') }}
                            </flux:button>
                        @else
                            <flux:button :href="route('login')" variant="ghost" data-test="welcome-login-link">
                                {{ __('Log in') }}
                            </flux:button>

                            @if (Route::has('register'))
                                <flux:button :href="route('register')" variant="primary" data-test="welcome-register-link">
                                    {{ __('Get started') }}
                                </flux:button>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <main>
                {{-- Hero --}}
                <section class="mx-auto grid w-full max-w-6xl items-center gap-12 px-6 pt-12 pb-20 lg:grid-cols-[1.1fr_1fr] lg:px-8 lg:pt-20 lg:pb-28">
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

                    @php
                        /**
                         * Three sample trails across the room, drawn the way the report's replay
                         * draws real ones: the hue sequence is the replay's (index * 67 degrees),
                         * with entry dots and exit arrowheads, and green/red bars where each trail
                         * crosses the frame edge. Saturation and lightness are the replay's own
                         * 85%/60% too — the room below is drawn at night, as the dimmed photo the
                         * replay lays its trails over, so nothing has to be pulled down to read.
                         *
                         * @var array<int, array{d: string, color: string, delay: string, start: array{int, int}, arrow: string}>
                         */
                        $heroTrails = [
                            [
                                'd' => 'M 2 172 C 44 168, 66 190, 108 182 S 178 158, 216 152 S 306 152, 392 158',
                                'color' => 'hsl(0 85% 60%)',
                                'delay' => '0s',
                                'start' => [2, 172],
                                'arrow' => 'M 397 158 L 383 153 L 383 164 Z',
                            ],
                            [
                                'd' => 'M 150 298 C 152 266, 178 250, 214 236 S 268 208, 252 186 S 196 176, 168 196 S 120 232, 76 240 L 4 248',
                                'color' => 'hsl(134 85% 60%)',
                                'delay' => '-1.5s',
                                'start' => [150, 298],
                                'arrow' => 'M 0 249 L 15 244 L 15 255 Z',
                            ],
                            [
                                'd' => 'M 32 102 C 30 124, 44 140, 64 148 S 120 158, 168 166 S 250 200, 292 236 S 340 276, 356 294',
                                'color' => 'hsl(201 85% 60%)',
                                'delay' => '-3s',
                                'start' => [32, 102],
                                'arrow' => 'M 358 298 L 347 289 L 356 284 Z',
                            ],
                        ];
                    @endphp

                    {{-- A night in progress, drawn the way the report replays it. Sample values only. --}}
                    <div aria-hidden="true" class="relative">
                        <div class="absolute -inset-4 -z-10 rounded-[2rem] bg-amber-300/30 blur-3xl dark:bg-amber-500/10"></div>

                        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/40">
                            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <div class="flex items-center gap-2">
                                    <span class="relative flex size-2.5">
                                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex size-2.5 rounded-full bg-green-500"></span>
                                    </span>
                                    <span class="text-sm font-medium">{{ __('Recording now') }}</span>
                                </div>
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Started 22:40') }}</span>
                            </div>

                            <div class="relative aspect-4/3 bg-zinc-900">
                                <svg viewBox="0 0 400 300" class="absolute inset-0 size-full" fill="none">
                                    <defs>
                                        {{-- The lamplight falls off rather than ending: a hard-edged
                                             ellipse here reads as a second rug, not as light. --}}
                                        <radialGradient id="welcome-hero-lamplight">
                                            <stop offset="0" stop-color="#a8a29e" stop-opacity="0.30" />
                                            <stop offset="0.55" stop-color="#a8a29e" stop-opacity="0.13" />
                                            <stop offset="1" stop-color="#a8a29e" stop-opacity="0" />
                                        </radialGradient>
                                    </defs>

                                    {{-- The reference frame: a living room at night, from a camera
                                         propped on a shelf. The room is drawn on the replay's own
                                         backdrop (#18181b) and lit only by the floor lamp on the
                                         right, so the palette stays within a few steps of it and
                                         the trails above are the brightest thing in the picture. --}}
                                    <rect width="400" height="300" fill="#18181b" />
                                    <rect width="400" height="100" fill="#202024" />
                                    <rect y="96" width="400" height="6" fill="#2a2a30" />

                                    {{-- The lamp's pool of light, warm and low. --}}
                                    <ellipse cx="354" cy="170" rx="150" ry="112" fill="url(#welcome-hero-lamplight)" />

                                    {{-- Doorway, left: an unlit hall, so darker than the wall it is in. --}}
                                    <rect x="12" y="12" width="44" height="84" rx="2" fill="#131316" stroke="#2c2c33" />

                                    {{-- Rug and coffee table. --}}
                                    <ellipse cx="222" cy="216" rx="118" ry="52" fill="#212126" stroke="#2a2a30" />
                                    <rect x="176" y="196" width="96" height="26" rx="4" fill="#2b2b32" stroke="#3a3a43" />
                                    <path d="M 182 222 v 10 M 266 222 v 10" stroke="#3a3a43" stroke-width="2" />

                                    {{-- Sofa, against the back wall. --}}
                                    <rect x="72" y="70" width="118" height="36" rx="6" fill="#27272d" />
                                    <rect x="66" y="100" width="130" height="30" rx="6" fill="#2e2e35" />
                                    <rect x="58" y="84" width="16" height="48" rx="5" fill="#25252b" />
                                    <rect x="188" y="84" width="16" height="48" rx="5" fill="#25252b" />
                                    <path d="M 110 102 v 26 M 152 102 v 26" stroke="#3a3a43" stroke-width="1.5" />

                                    {{-- Media unit, television and the floor lamp lighting the room. --}}
                                    <rect x="262" y="96" width="94" height="26" rx="3" fill="#27272d" stroke="#36363e" />
                                    <rect x="286" y="52" width="62" height="40" rx="2" fill="#141418" stroke="#33333b" />
                                    <path d="M 317 92 v 4" stroke="#33333b" stroke-width="3" />
                                    <path d="M 376 118 v 78" stroke="#33333b" stroke-width="3" />
                                    <ellipse cx="376" cy="198" rx="13" ry="5" fill="#2b2b32" />
                                    <path d="M 362 118 L 390 118 L 384 96 L 368 96 Z" fill="#5a4a2c" stroke="#6f5c36" />

                                    {{-- Where the trails cross the frame edge, as the report marks them. --}}
                                    <rect x="0" y="160" width="4" height="26" fill="rgb(74 222 128 / 0.65)" />
                                    <rect x="136" y="296" width="32" height="4" fill="rgb(74 222 128 / 0.65)" />
                                    <rect x="396" y="144" width="4" height="28" fill="rgb(248 113 113 / 0.65)" />
                                    <rect x="0" y="236" width="4" height="26" fill="rgb(248 113 113 / 0.65)" />
                                    <rect x="340" y="296" width="34" height="4" fill="rgb(248 113 113 / 0.65)" />

                                    @foreach ($heroTrails as $trail)
                                        <g data-test="welcome-hero-trail">
                                            {{-- The settled trail, with the crawling dashes of the replay on top of it. --}}
                                            <path d="{{ $trail['d'] }}" stroke="{{ $trail['color'] }}" stroke-width="2.5" stroke-linecap="round" opacity="0.3" />
                                            <path
                                                d="{{ $trail['d'] }}"
                                                stroke="{{ $trail['color'] }}"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                                stroke-dasharray="6 6"
                                                style="animation-delay: {{ $trail['delay'] }}"
                                                class="motion-safe:animate-trail"
                                            />
                                            <circle cx="{{ $trail['start'][0] }}" cy="{{ $trail['start'][1] }}" r="9" fill="{{ $trail['color'] }}" opacity="0.2" />
                                            <circle cx="{{ $trail['start'][0] }}" cy="{{ $trail['start'][1] }}" r="4" fill="{{ $trail['color'] }}" />
                                            <path d="{{ $trail['arrow'] }}" fill="{{ $trail['color'] }}" />
                                        </g>
                                    @endforeach
                                </svg>

                                <div class="absolute top-3 left-3 flex items-center gap-1.5 rounded-md bg-black/60 px-2 py-1 text-xs font-medium text-amber-300 backdrop-blur">
                                    <flux:icon name="map-pin" variant="micro" />
                                    {{ __('3 trails, in by the door and the left edge') }}
                                </div>
                                <div class="absolute bottom-3 left-3 rounded-md bg-black/60 px-2 py-1 font-mono text-xs text-zinc-300 backdrop-blur">02:47:12</div>
                            </div>

                            <dl class="grid grid-cols-3 divide-x divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                                <div class="px-5 py-4">
                                    <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ __('Sightings') }}</dt>
                                    <dd class="mt-1 text-2xl font-semibold tabular-nums">7</dd>
                                </div>
                                <div class="px-5 py-4">
                                    <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ __('Last seen') }}</dt>
                                    <dd class="mt-1 text-2xl font-semibold tabular-nums">02:47</dd>
                                </div>
                                <div class="px-5 py-4">
                                    <dt class="text-xs text-zinc-500 uppercase dark:text-zinc-400">{{ __('Room') }}</dt>
                                    <dd class="mt-1 truncate text-xl font-semibold">{{ __('Living room') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                {{-- How it works --}}
                <section class="border-y border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div class="mx-auto w-full max-w-6xl px-6 py-16 lg:px-8 lg:py-20">
                        <div class="max-w-2xl">
                            <flux:heading size="xl" level="2">{{ __('Three steps, one night') }}</flux:heading>
                            <flux:text class="mt-2 text-base">{{ __('There is nothing to install and nothing to calibrate. The browser does the watching.') }}</flux:text>
                        </div>

                        <ol class="mt-10 grid gap-6 md:grid-cols-3">
                            <li class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                                <span class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">1</span>
                                <flux:heading size="lg" level="3">{{ __('Prop up a camera') }}</flux:heading>
                                <flux:text>{{ __('Open the capture page on any phone or laptop, name the room, and aim it at the floor where you have seen activity. Plug it in and leave the screen on.') }}</flux:text>
                            </li>
                            <li class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                                <span class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">2</span>
                                <flux:heading size="lg" level="3">{{ __('Sleep') }}</flux:heading>
                                <flux:text>{{ __('Motion is tracked on the device itself. Only the finished trails and a small verification crop of each one are sent up, never a video stream.') }}</flux:text>
                            </li>
                            <li class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                                <span class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">3</span>
                                <flux:heading size="lg" level="3">{{ __('Read the report') }}</flux:heading>
                                <flux:text>{{ __('End the night and every sighting is laid out with its path, first and last seen times, and a crop you can confirm or mark as not a bug.') }}</flux:text>
                            </li>
                        </ol>
                    </div>
                </section>

                {{-- The no-account path. Shown only to guests, because it is an answer to
                     "do I have to sign up first": the same capture and report, kept on the
                     visitor's own device instead of in an account. --}}
                @guest
                    <section class="mx-auto w-full max-w-6xl px-6 py-16 lg:px-8 lg:py-20" data-test="welcome-demo-section">
                        <div class="rounded-2xl border border-amber-500/30 bg-amber-50/60 p-8 lg:p-10 dark:border-amber-500/20 dark:bg-amber-500/5">
                            <div class="grid gap-8 lg:grid-cols-[1.15fr_1fr] lg:items-center">
                                <div class="flex flex-col gap-4">
                                    <flux:badge color="amber" size="sm" icon="eye" class="w-fit">{{ __('No account needed') }}</flux:badge>

                                    <flux:heading size="xl" level="2">{{ __('Watch a room tonight without signing up.') }}</flux:heading>

                                    <flux:text class="max-w-xl text-base">
                                        {{ __('This is the real thing rather than a walkthrough. The same detection runs, the same night is recorded, and the same report is waiting in the morning. It all happens inside your browser, so there is no account to create and nothing to cancel afterwards.') }}
                                    </flux:text>

                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                                        <flux:button :href="route('watch.capture')" variant="primary" icon="play" data-test="welcome-demo-link">
                                            {{ __('Start watching now') }}
                                        </flux:button>
                                        <flux:text class="text-sm">{{ __('Change your mind later and your nights can come with you.') }}</flux:text>
                                    </div>
                                </div>

                                <ul class="grid gap-3">
                                    <li class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                        <flux:icon name="check-circle" variant="micro" class="mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        <flux:text class="text-sm">{{ __('The whole detector, the overnight capture and the morning report, exactly as an account gets them.') }}</flux:text>
                                    </li>
                                    <li class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                        <flux:icon name="lock-closed" variant="micro" class="mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        <flux:text class="text-sm">{{ __('Nothing is uploaded. The night is kept in this browser, on this device, and you can delete it in one tap.') }}</flux:text>
                                    </li>
                                    <li class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                        <flux:icon name="information-circle" variant="micro" class="mt-0.5 shrink-0 text-zinc-500" />
                                        <flux:text class="text-sm">{{ __('An account is what adds trends across nights, the entry point map, and reading a report from another device.') }}</flux:text>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>
                @endguest

                {{-- Features --}}
                <section class="mx-auto w-full max-w-6xl px-6 py-16 lg:px-8 lg:py-20">
                    <div class="max-w-2xl">
                        <flux:heading size="xl" level="2">{{ __('One night tells you where. A month tells you whether it is working.') }}</flux:heading>
                        <flux:text class="mt-2 text-base">{{ __('Every session feeds the same set of views, so the picture sharpens the longer you watch.') }}</flux:text>
                    </div>

                    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700">
                            <flux:icon name="chart-bar" class="size-6 text-amber-600 dark:text-amber-400" />
                            <flux:heading size="lg" level="3">{{ __('Trends') }}</flux:heading>
                            <flux:text>{{ __('Confirmed sightings per night, charted over time. Bait, sealed gaps and clean-ups are numbered on the chart so you can see what happened after each one.') }}</flux:text>
                        </article>
                        <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700">
                            <flux:icon name="map-pin" class="size-6 text-amber-600 dark:text-amber-400" />
                            <flux:heading size="lg" level="3">{{ __('Entry points') }}</flux:heading>
                            <flux:text>{{ __('Entry and exit zones from every night in a room, stacked onto one backdrop. The gap they keep using is the one worth sealing.') }}</flux:text>
                        </article>
                        <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700">
                            <flux:icon name="wrench-screwdriver" class="size-6 text-amber-600 dark:text-amber-400" />
                            <flux:heading size="lg" level="3">{{ __('Interventions') }}</flux:heading>
                            <flux:text>{{ __('Log what you did and when. The next nights are compared against the ones before, so you know whether the gel bait under the sink earned its keep.') }}</flux:text>
                        </article>
                        <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700">
                            <flux:icon name="home-modern" class="size-6 text-amber-600 dark:text-amber-400" />
                            <flux:heading size="lg" level="3">{{ __('Rooms') }}</flux:heading>
                            <flux:text>{{ __('Name the room when you start and the nights group themselves. Typos merge with a rename, and the same room name in two properties stays two rooms.') }}</flux:text>
                        </article>
                        <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700">
                            <flux:icon name="users" class="size-6 text-amber-600 dark:text-amber-400" />
                            <flux:heading size="lg" level="3">{{ __('Customers') }}</flux:heading>
                            <flux:text>{{ __('Built for pest technicians as much as homeowners. Group nights by property, filter every view to one customer, and hand over a report that shows the work.') }}</flux:text>
                        </article>
                        <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700">
                            <flux:icon name="device-phone-mobile" class="size-6 text-amber-600 dark:text-amber-400" />
                            <flux:heading size="lg" level="3">{{ __('Check in from bed') }}</flux:heading>
                            <flux:text>{{ __('The dashboard shows the night in progress from any other device: sightings so far, the last one seen, and a warning if the camera has gone quiet.') }}</flux:text>
                        </article>
                    </div>
                </section>

                {{-- Privacy --}}
                <section class="mx-auto w-full max-w-6xl px-6 pb-16 lg:px-8 lg:pb-20">
                    <div class="flex flex-col gap-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-8 md:flex-row md:items-center md:justify-between dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div class="flex items-start gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                                <flux:icon name="lock-closed" class="size-5" />
                            </span>
                            <div>
                                <flux:heading size="lg" level="2">{{ __('It is a camera inside your home. We treat it that way.') }}</flux:heading>
                                <flux:text class="mt-1 max-w-2xl">{{ __('Reference frames and crops are stored privately and served only to the account that recorded them, with caching switched off. Delete a night and its images go with it.') }}</flux:text>
                            </div>
                        </div>

                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" class="shrink-0">
                                {{ __('Go to the dashboard') }}
                            </flux:button>
                        @else
                            @if (Route::has('register'))
                                <flux:button :href="route('register')" variant="primary" class="shrink-0">
                                    {{ __('Start watching tonight') }}
                                </flux:button>
                            @endif
                        @endauth
                    </div>
                </section>
            </main>

            <footer class="border-t border-zinc-200 dark:border-zinc-800">
                <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-6 py-8 text-sm text-zinc-500 sm:flex-row lg:px-8 dark:text-zinc-400">
                    <div class="flex items-center gap-2">
                        <x-app-logo-icon class="size-4 fill-current" />
                        <span>{{ config('app.name', 'BugTracker') }}</span>
                    </div>
                    <p>{{ __('Watch the room. Seal the gap. Sleep better.') }}</p>
                </div>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
