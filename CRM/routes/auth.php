<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/*
| Self-contained auth: login, logout, forgot + reset password.
| White-label: views pull brand from shared $brand variable — no vendor names.
*/
Route::middleware('installed')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
    Route::post('/login', function (Request $r) {
        $c = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        // Disabled accounts cannot sign in.
        $user = User::where('email', $c['email'])->first();
        if ($user && ! $user->is_active) {
            return back()->withErrors(['email' => 'This account has been disabled.']);
        }
        if (! Hash::check($c['password'], $user->password ?? '')) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }
        // Two-factor: park the id in session, verify on the challenge page.
        if ($user->twofa_enabled && $user->twofa_secret) {
            $r->session()->put('twofa:user_id', $user->id);
            $r->session()->put('twofa:remember', $r->boolean('remember'));

            return redirect()->route('2fa.challenge');
        }
        Auth::login($user, $r->boolean('remember'));
        $r->session()->regenerate();

        return redirect()->intended('/');
    })->middleware(['guest', 'throttle:10,1']);
    Route::get('/login/2fa', fn () => session()->has('twofa:user_id') ? view('auth.twofa') : redirect('/login'))->middleware('guest')->name('2fa.challenge');
    Route::post('/login/2fa', function (Request $r) {
        $data = $r->validate(['code' => 'required|string|max:32']);
        $user = User::find($r->session()->get('twofa:user_id'));
        if (! $user || ! $user->twofa_enabled || ! $user->twofa_secret) {
            return redirect('/login');
        }
        $ok = totp_verify($user->twofa_secret, $data['code']);
        if (! $ok) {
            // Recovery codes (single use).
            $codes = json_decode($user->twofa_recovery_codes ?? '[]', true) ?: [];
            foreach ($codes as $i => $hash) {
                if (Hash::check($data['code'], $hash)) {
                    unset($codes[$i]);
                    $user->update(['twofa_recovery_codes' => json_encode(array_values($codes))]);
                    $ok = true;
                    break;
                }
            }
        }
        if (! $ok) {
            return back()->withErrors(['code' => 'Wrong code. Try again.']);
        }
        Auth::login($user, $r->session()->pull('twofa:remember', false));
        $r->session()->forget('twofa:user_id');
        $r->session()->regenerate();

        return redirect()->intended('/');
    })->middleware(['guest', 'throttle:10,1'])->name('2fa.verify');
    Route::post('/logout', function (Request $r) {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    })->middleware('auth')->name('logout');

    Route::get('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('guest')->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware(['guest', 'throttle:5,1'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->middleware('guest')->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware(['guest', 'throttle:5,1'])->name('password.update');
});
