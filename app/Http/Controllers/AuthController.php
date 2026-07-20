<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('churches.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => __('auth.login_failed')])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            $request->session()->put('otp_user_id', $user->id);

            return redirect()->route('verify-otp')->with('error', __('auth.login_unverified'));
        }

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => __('auth.account_deactivated')])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('churches.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
