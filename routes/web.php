<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Contact;
use App\Livewire\Dashboard;
use App\Livewire\Home;
use App\Livewire\Permissions\TablePermissions;
use App\Livewire\Profile;
use App\Livewire\Roles\TableRoles;
use App\Livewire\Roles\UpdateRole;
use App\Livewire\Users\TableUsers;
use App\Livewire\Users\UpdateUser;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
Route::livewire('/contacto', Contact::class)->name('contact');
Route::post('/logout', LogoutController::class)->name('logout');

Route::middleware(['auth'])
    ->group(function () {
        Route::livewire('/dashboard', Dashboard::class)->name('dashboard');
        Route::livewire('/perfil-usuario', Profile::class)->name('profile');

        Route::prefix('sistema')->group(function () {
            Route::livewire('/usuarios', TableUsers::class)->name('users.table');
            Route::livewire('/usuarios/editar/{user}', UpdateUser::class)->name('users.update');
        });

        Route::prefix('sistema')->group(function () {
            Route::livewire('/cargos', TableRoles::class)->name('roles.table');
            Route::livewire('/cargos/editar/{role}', UpdateRole::class)->name('roles.update');
        });

        Route::prefix('sistema')->group(function () {
            Route::livewire('/permisos', TablePermissions::class)->name('permissions.table');
        });
    });

// fallback
Route::fallback(function () {
    return redirect()->route('home');
});

require __DIR__.'/auth.php';
