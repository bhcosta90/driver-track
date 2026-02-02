<?php

declare(strict_types = 1);

use App\Livewire\User\Profile;
use App\Livewire\Users\Index;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/users', Index::class)->name('users.index');

    Route::get('/user/profile', Profile::class)->name('user.profile');
});

require __DIR__ . '/auth.php';
