<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        {{-- The guest pages: no sidebar, because there is no account to navigate, and
             nothing that dereferences a user. data-app-nav marks the chrome capture.js
             makes inert while a night is recording; leaving this page ends the night. --}}
        <header data-app-nav class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5 transition-opacity lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold">
                <span class="bg-accent-content flex size-9 items-center justify-center rounded-lg text-white dark:text-black">
                    <x-app-logo-icon class="size-5 fill-current" />
                </span>
                <span>{{ config('app.name', 'BugTracker') }}</span>
            </a>

            <nav class="flex items-center gap-2" aria-label="{{ __('Account') }}">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" icon-trailing="arrow-right">
                        {{ __('Dashboard') }}
                    </flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost">
                        {{ __('Log in') }}
                    </flux:button>
                    <flux:button :href="route('register')">
                        {{ __('Get started') }}
                    </flux:button>
                @endauth
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 pb-16 lg:px-8">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
