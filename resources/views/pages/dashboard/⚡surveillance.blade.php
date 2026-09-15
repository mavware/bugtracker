<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard'), Layout('layouts::app', [
    'heading' => 'Surveillance',
    'subHeading' => 'Overnight bug watching sessions',
])] class extends Component {
}; ?>

<section>
    <livewire:surveillance.tonight />

    <x-surveillance.claim-nights />

    <livewire:surveillance.sessions />

</section>
