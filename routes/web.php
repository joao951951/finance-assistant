<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');

    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::delete('imports/{rawImport}', [ImportController::class, 'destroy'])->name('imports.destroy');

    Route::get('chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::post('chat', [ConversationController::class, 'store'])->name('chat.store');
    Route::get('chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::delete('chat/{conversation}', [ConversationController::class, 'destroy'])->name('chat.destroy');
    Route::post('chat/{conversation}/messages', [MessageController::class, 'store'])->name('chat.messages.store');
});

require __DIR__.'/settings.php';
