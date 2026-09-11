<x-layouts::app
    :title="__('Dashboard')"
    :heading="__('Surveillance')"
    :sub-heading="__('Overnight bug watching sessions')">

    <livewire:surveillance.tonight />

    <x-surveillance.claim-nights />

    <livewire:surveillance.sessions />

</x-layouts::app>
