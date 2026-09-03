<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('/', 'pages::admin.index')->name('admin.index');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');
    Route::livewire('sessions', 'pages::admin.sessions')->name('admin.sessions');
    Route::livewire('rooms', 'pages::admin.rooms')->name('admin.rooms');
    Route::livewire('customers', 'pages::admin.customers')->name('admin.customers');
});
