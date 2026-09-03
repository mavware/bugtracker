<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\Customer;
use App\Models\Intervention;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin')] class extends Component {
    /**
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'users' => User::count(),
            'admins' => User::where('is_admin', true)->count(),
            'customers' => Customer::count(),
            'sessions' => SurveillanceSession::count(),
            'recording' => SurveillanceSession::where('status', SurveillanceSessionStatus::Active)->count(),
            'tracks' => BugTrack::count(),
            'interventions' => Intervention::count(),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Site administration') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Everything recorded across every account.') }}</flux:text>
        </div>
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
    </div>

    <div class="mt-6">
        <x-admin.nav />
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => __('Users'), 'value' => $this->totals['users'], 'note' => trans_choice(':count admin|:count admins', $this->totals['admins'], ['count' => $this->totals['admins']])],
            ['label' => __('Customers'), 'value' => $this->totals['customers'], 'note' => null],
            ['label' => __('Sessions'), 'value' => $this->totals['sessions'], 'note' => $this->totals['recording'] > 0 ? __(':count recording now', ['count' => $this->totals['recording']]) : __('None recording')],
            ['label' => __('Sightings'), 'value' => $this->totals['tracks'], 'note' => __(':count interventions', ['count' => $this->totals['interventions']])],
        ] as $tile)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ $tile['label'] }}</flux:text>
                <flux:heading size="xl">{{ $tile['value'] }}</flux:heading>
                @if ($tile['note'] !== null)
                    <flux:text class="mt-1 text-sm">{{ $tile['note'] }}</flux:text>
                @endif
            </div>
        @endforeach
    </div>

    <flux:callout icon="lock-closed" class="mt-6">
        <flux:callout.heading>{{ __('Recordings stay private') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('These screens manage accounts and metadata only. Reference photos and sighting snapshots are pictures of the inside of someone\'s home, so they remain visible to their owner alone — being an admin does not open them.') }}
        </flux:callout.text>
    </flux:callout>
</section>
