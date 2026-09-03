<?php

use App\Http\Controllers\Surveillance\CaptureController;
use App\Http\Controllers\Surveillance\ImageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('surveillance/customers', 'pages::surveillance.customers')->name('surveillance.customers');
    Route::livewire('surveillance/heatmap', 'pages::surveillance.heatmap')->name('surveillance.heatmap');
    Route::livewire('surveillance/trends', 'pages::surveillance.trends')->name('surveillance.trends');
    Route::livewire('surveillance/{session}/capture', 'pages::surveillance.capture')->name('surveillance.capture');
    Route::livewire('surveillance/{session}/report', 'pages::surveillance.report')->name('surveillance.report');

    Route::middleware('throttle:120,1')->group(function () {
        Route::post('surveillance/{session}/reference', [CaptureController::class, 'storeReference'])->name('surveillance.reference.store');
        Route::post('surveillance/{session}/tracks', [CaptureController::class, 'storeTracks'])->name('surveillance.tracks.store');
        Route::post('surveillance/{session}/heartbeat', [CaptureController::class, 'heartbeat'])->name('surveillance.heartbeat');
        Route::post('surveillance/{session}/end', [CaptureController::class, 'end'])->name('surveillance.end');
    });

    Route::get('surveillance/{session}/reference-image', [ImageController::class, 'showReference'])->name('surveillance.reference.show');
    Route::get('surveillance/{session}/tracks/{track}/crop/{position}', [ImageController::class, 'showCrop'])
        ->whereIn('position', ['start', 'end'])
        ->scopeBindings()
        ->name('surveillance.crop.show');
});
