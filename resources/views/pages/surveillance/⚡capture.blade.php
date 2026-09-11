<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Capture')] class extends Component {
    public SurveillanceSession $session;

    public function mount(SurveillanceSession $session): void
    {
        Gate::authorize('update', $session);

        /*
         * Only a pending night belongs here. A finished one has its report, and
         * an active one is being recorded on another device: showing Start again
         * would re-upload the reference frame and reset started_at, mis-timing
         * every sighting stored so far. Its page shows how it is going instead.
         */
        if ($session->status !== SurveillanceSessionStatus::Pending) {
            $this->redirectRoute('surveillance.report', $session);

            return;
        }

        $this->session = $session;
    }

    /**
     * Everything the capture script needs, and nothing it does not: the page is
     * the only source of these, so an unread key is dead weight in the markup.
     * After ending the night the script follows the report_url the server returns
     * rather than a route handed over up front.
     *
     * @return array{csrfToken: string, routes: array{reference: string, tracks: string, heartbeat: string, end: string}}
     */
    public function captureConfig(): array
    {
        return [
            'csrfToken' => csrf_token(),
            'routes' => [
                'reference' => route('surveillance.reference.store', $this->session),
                'tracks' => route('surveillance.tracks.store', $this->session),
                'heartbeat' => route('surveillance.heartbeat', $this->session),
                'end' => route('surveillance.end', $this->session),
            ],
        ];
    }
}; ?>

<x-surveillance.capture-panel
    :config="$this->captureConfig()"
    :name="$session->name"
    mode="server"
>
    {{-- Night-time reading, so it is in the slot that appears once the night is
         under way rather than the one that leaves with the hero. The panel's own
         box beside it already says to keep the device plugged in and awake. --}}
    <x-slot:nightHelp>
        <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Checking on it from bed') }}</flux:heading>
            <p class="mt-2">
                {{ __('Leave this device be — opening anything else on it ends the night. To see how it is going, open your dashboard on a different phone or computer:') }}
            </p>
            {{-- Deliberately not a link: following it here would end the recording. --}}
            <p class="mt-2 font-mono text-xs break-all text-zinc-700 dark:text-zinc-300">{{ route('dashboard') }}</p>
            <p class="mt-2">
                {{ __('It shows sightings so far, when the last one was, and warns you if this device stops checking in.') }}
            </p>
        </div>
    </x-slot:nightHelp>
</x-surveillance.capture-panel>
