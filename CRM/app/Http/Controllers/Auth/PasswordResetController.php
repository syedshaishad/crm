<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgot()
    {
        return view('auth.forgot');
    }

    public function sendLink(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($r->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('ok', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetForm(string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => request('email')]);
    }

    public function reset(Request $r)
    {
        $r->validate([
            'token' => 'required', 'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('ok', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
