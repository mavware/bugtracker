<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('customers from every account are listed and searchable by owner email', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create(['email' => 'dana@example.com']);
    Customer::factory()->for($owner)->create(['name' => 'The Alvarez house']);
    Customer::factory()->create(['name' => 'A different firm\'s account']);

    Livewire::actingAs($admin)
        ->test('pages::admin.customers')
        ->assertSee('The Alvarez house')
        ->assertSee('A different firm\'s account')
        ->set('search', 'dana@example.com')
        ->assertSee('The Alvarez house')
        ->assertDontSee('A different firm\'s account');
});

test('a customer can be renamed', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create(['name' => 'The Alvarez hosue']);

    Livewire::actingAs($admin)
        ->test('pages::admin.customers')
        ->call('startRename', $customer->id)
        ->assertSet('name', 'The Alvarez hosue')
        ->set('name', 'The Alvarez house')
        ->call('renameCustomer')
        ->assertHasNoErrors();

    expect($customer->refresh()->name)->toBe('The Alvarez house');
});

test('a rename cannot collide with another name the same owner already uses', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    Customer::factory()->for($owner)->create(['name' => 'The Alvarez house']);
    $customer = Customer::factory()->for($owner)->create(['name' => 'The Brody house']);

    Livewire::actingAs($admin)
        ->test('pages::admin.customers')
        ->call('startRename', $customer->id)
        ->set('name', 'The Alvarez house')
        ->call('renameCustomer')
        ->assertHasErrors(['name' => 'unique']);

    expect($customer->refresh()->name)->toBe('The Brody house');
});

test('a rename may reuse a name belonging to a different owner', function () {
    $admin = User::factory()->admin()->create();
    Customer::factory()->create(['name' => 'The Alvarez house']);
    $customer = Customer::factory()->create(['name' => 'The Brody house']);

    Livewire::actingAs($admin)
        ->test('pages::admin.customers')
        ->call('startRename', $customer->id)
        ->set('name', 'The Alvarez house')
        ->call('renameCustomer')
        ->assertHasNoErrors();

    expect($customer->refresh()->name)->toBe('The Alvarez house');
});

test('removing a customer keeps their recorded nights', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create();
    $session = SurveillanceSession::factory()->for($owner)->completed()->create(['customer_id' => $customer->id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.customers')
        ->call('deleteCustomer', $customer->id);

    expect(Customer::query()->find($customer->id))->toBeNull()
        ->and($session->refresh()->customer_id)->toBeNull();
});
