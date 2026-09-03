<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('surveillance.customers'))->assertRedirect(route('login'));
});

test('the page lists only the current user\'s customers', function () {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    Customer::factory()->create(['name' => 'Another firm\'s account']);

    $this->actingAs($user)
        ->get(route('surveillance.customers'))
        ->assertSee('The Alvarez house')
        ->assertDontSee('Another firm\'s account');
});

test('a customer can be added', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.customers')
        ->set('name', 'The Alvarez house')
        ->set('address', '12 Oak Street')
        ->call('save')
        ->assertHasNoErrors();

    $customer = $user->customers()->sole();
    expect($customer->name)->toBe('The Alvarez house')
        ->and($customer->address)->toBe('12 Oak Street');
});

test('a customer needs a name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.customers')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect($user->customers()->count())->toBe(0);
});

test('the same customer name cannot be added twice, but two users may share one', function () {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    Customer::factory()->create(['name' => 'The Alvarez house']);

    Livewire::actingAs($user)
        ->test('pages::surveillance.customers')
        ->set('name', 'The Alvarez house')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect($user->customers()->count())->toBe(1);
});

test('a customer can be renamed without tripping its own uniqueness rule', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create(['name' => 'The Alvarez house', 'address' => '12 Oak Street']);

    Livewire::actingAs($user)
        ->test('pages::surveillance.customers')
        ->call('edit', $customer->id)
        ->assertSet('name', 'The Alvarez house')
        ->set('address', '14 Oak Street')
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->refresh()->address)->toBe('14 Oak Street')
        ->and($customer->name)->toBe('The Alvarez house');
});

test('removing a customer keeps their recorded nights and un-groups them', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create(['customer_id' => $customer->id]);

    Livewire::actingAs($user)
        ->test('pages::surveillance.customers')
        ->call('deleteCustomer', $customer->id);

    expect(Customer::query()->find($customer->id))->toBeNull()
        ->and($session->refresh()->customer_id)->toBeNull();
});

test('another user\'s customer cannot be edited', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::surveillance.customers')
        ->call('edit', $customer->id);
})->throws(ModelNotFoundException::class);

test('another user\'s customer cannot be removed', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::surveillance.customers')
        ->call('deleteCustomer', $customer->id);
})->throws(ModelNotFoundException::class);
