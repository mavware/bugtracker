<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- data-app-nav marks the app chrome that capture.js makes inert while a night
             is recording; leaving this page ends the night. --}}
        <flux:sidebar data-app-nav sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 transition-opacity dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group
                    expandable
                    icon="home"
                    :heading="__('Dashboard')"
                    :expanded="request()->routeIs('dashboard') || request()->routeIs('surveillance.*')"
                >
                    <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Surveillance') }}
                    </flux:sidebar.item>
                    @can(\App\Enums\Permission::ManageCustomers->value)
                        <flux:sidebar.item icon="users" :href="route('surveillance.customers')" :current="request()->routeIs('surveillance.customers')">
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                    @endcan
                    <flux:sidebar.item icon="chart-bar" :href="route('surveillance.trends')" :current="request()->routeIs('surveillance.trends')">
                        {{ __('Trends') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="home-modern" :href="route('surveillance.rooms')" :current="request()->routeIs('surveillance.rooms')">
                        {{ __('Rooms') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Access to the portal is a matter of record, not role: it appears once
                     a professional has linked a property to this account. --}}
                @if (auth()->user()->isPortalClient())
                    <flux:sidebar.group
                        expandable
                        icon="key"
                        :heading="__('Client portal')"
                        :expanded="request()->routeIs('portal.*')"
                    >
                        <flux:sidebar.item icon="building-office-2" :href="route('portal.index')" :current="request()->routeIs('portal.index')">
                            {{ __('Properties') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="home-modern" :href="route('portal.rooms')" :current="request()->routeIs('portal.rooms')">
                            {{ __('Rooms') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @can(\App\Enums\Permission::AccessAdmin->value)
                    <flux:sidebar.group
                        expandable
                        icon="shield-check"
                        :heading="__('Administration')"
                        :expanded="request()->routeIs('admin.*')"
                    >
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.index')" :current="request()->routeIs('admin.index')">
                            {{ __('Overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')">
                            {{ __('Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="video-camera" :href="route('admin.sessions')" :current="request()->routeIs('admin.sessions')">
                            {{ __('Sessions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="home-modern" :href="route('admin.rooms')" :current="request()->routeIs('admin.rooms')">
                            {{ __('Rooms') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="briefcase" :href="route('admin.customers')" :current="request()->routeIs('admin.customers')">
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header data-app-nav class="transition-opacity lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <x-confirm-dialog />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
