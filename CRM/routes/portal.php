<?php

use App\Http\Controllers\PortalController;
use App\Models\ClientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/*
| Client portal: separate session guard (`client`), separate table, zero
| access to workspace routes. Document view/sign/pay reuse the public
| token links — the portal lists and links, never duplicates them.
*/
Route::middleware('installed')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', fn () => view('portal.login'))->middleware('guest:client')->name('login');
    Route::post('/login', function (Request $r) {
        $c = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = ClientUser::where('email', $c['email'])->first();
        if ($user && (! $user->is_active || ! $user->client)) {
            return back()->withErrors(['email' => 'This portal access has been disabled.']);
        }
        if (! $user || ! Hash::check($c['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }
        Auth::guard('client')->login($user, $r->boolean('remember'));
        $r->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    })->middleware(['guest:client', 'throttle:10,1']);

    Route::middleware(['auth:client', 'client.active', 'license'])->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/invoices', [PortalController::class, 'invoices'])->name('invoices');
        Route::get('/proposals', [PortalController::class, 'proposals'])->name('proposals');
        Route::get('/receipts', [PortalController::class, 'receipts'])->name('receipts');
        Route::get('/statement', [PortalController::class, 'statement'])->name('statement');
        Route::get('/work', [PortalController::class, 'work'])->name('work');
        Route::post('/work/{project}/feedback', [PortalController::class, 'feedback'])->name('work.feedback');
        Route::get('/password', [PortalController::class, 'passwordForm'])->name('password');
        Route::post('/password', [PortalController::class, 'passwordStore'])->name('password.store');
        Route::post('/logout', function (Request $r) {
            Auth::guard('client')->logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return redirect()->route('portal.login');
        })->name('logout');
    });
});
