<?php

use App\Models\User;

// Every "are you sure?" in the app is asked through one dialog the layout
// renders, so a page script or a wire:confirm button finds it wherever it is.
test('the app layout renders the shared confirm dialog', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="confirm-dialog"', false)
        ->assertSee('data-test="confirm-dialog-cancel"', false)
        ->assertSee('data-test="confirm-dialog-accept"', false)
        ->assertSee('data-test="confirm-dialog-accept-danger"', false);
});

// The welcome page and the watch layout each carry the list of nights kept on
// the device, whose Remove button asks before deleting.
test('the guest pages render the shared confirm dialog', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-test="confirm-dialog"', false)
        ->assertSee('data-test="confirm-dialog-accept-danger"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('welcome'))
        ->assertOk()
        ->assertSee('data-test="confirm-dialog"', false);
});
