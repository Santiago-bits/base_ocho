<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_INTENTOS = 5;

    private const BLOQUEO_SEGUNDOS = 300;

    public function form(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $clave = 'login:'.Str::lower($datos['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $minutos = (int) ceil(RateLimiter::availableIn($clave) / 60);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Probá de nuevo en {$minutos} min.",
            ]);
        }

        if (! Auth::attempt($datos, $request->boolean('recordar'))) {
            RateLimiter::hit($clave, self::BLOQUEO_SEGUNDOS);
            throw ValidationException::withMessages(['email' => 'Email o contraseña incorrectos.']);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
