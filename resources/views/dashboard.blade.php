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

        <div class="h-full flex-1 overflow-auto rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <livewire:surveillance.sessions />
        </div>
    </div>
</x-layouts::app>
