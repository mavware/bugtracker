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

            <header data-app-nav class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5 transition-opacity lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold">
                    <span class="bg-accent-content flex size-9 items-center justify-center rounded-lg text-white dark:text-black">
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
                                <flux:button :href="route('register')" data-test="welcome-register-link">
                                    {{ __('Get started') }}
                                </flux:button>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <main>
                {{-- Hero: the watch panel itself rather than a picture of one. Its
                     side column carries the copy while idle and the night-time
                     reading once a night starts; the camera keeps two thirds of the
                     width throughout. data-test marks where the sample room is
                     drawn, so a test can count its trail within the hero alone. --}}
                <section class="mx-auto w-full max-w-6xl px-6 pt-8 pb-16 lg:px-8 lg:pt-12 lg:pb-24" data-test="welcome-hero-preview">
                    <x-surveillance.watch-panel :config="$config" />
                </section>

                {{-- Everything below is reading for an idle page. Starting a night
                     makes it inert and fades it, like the header: each link here
                     leaves the page, and leaving ends the night. --}}
                <div data-app-nav class="transition-opacity">
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
                                    <flux:text>{{ __('On any phone or laptop, aim the camera at the floor where you have seen activity, run through the room checklist, and press Start. Plug the device in and leave the screen on.') }}</flux:text>
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
                                        {{ __('Create a free account') }}
                                    </flux:button>
                                @endif
                            @endauth
                        </div>
                    </section>
                </div>
            </main>

            <footer class="border-t border-zinc-200 dark:border-zinc-800">
                <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-6 py-8 text-sm text-zinc-500 sm:flex-row lg:px-8 dark:text-zinc-400">
                    <div class="flex items-center gap-2">
                        <x-app-logo-icon class="text-accent-content size-4 fill-current" />
                        <span>{{ config('app.name', 'BugTracker') }}</span>
                    </div>
                    <p>{{ __('Watch the room. Seal the gap. Sleep better.') }}</p>
                </div>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
