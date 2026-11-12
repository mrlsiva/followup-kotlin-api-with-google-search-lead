<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $key = 'web-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('Too many login attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }
        RateLimiter::clear($key);

        $user = $request->user();
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => __('This account has been disabled. Contact your administrator.')]);
        }
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRegister(): View
    {
        abort_unless(config('crm.allow_registration'), 404);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(config('crm.allow_registration'), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = User::create([...$data, 'locale' => app()->getLocale()]);
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('user.registered', $user);

        return redirect()->route('dashboard');
    }

    /** Language switch: saved on the user, or in the session on the login page. */
    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('crm.locales')), 404);
        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
