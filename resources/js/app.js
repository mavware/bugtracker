import { themeLivewireConfirms } from './confirmDialog.js';

// Registered before Livewire starts (this module is deferred, Livewire starts on
// DOMContentLoaded), so wire:confirm buttons ask the app's dialog, not the
// browser's.
document.addEventListener('livewire:init', () => {
    themeLivewireConfirms(window.Livewire);
});
