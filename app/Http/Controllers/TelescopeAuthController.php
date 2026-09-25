<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Requests\TelescopeLoginRequest;
use App\Services\Auth\AdminAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TelescopeAuthController extends Controller
{
    public function __construct(private readonly AdminAuthService $authService) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::guard('telescope')->check()) {
            return redirect($this->dashboardPath());
        }

        return view('telescope.login');
    }

    public function login(TelescopeLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        try {
            $administrator = $this->authService->authenticate(
                $credentials['username'],
                $credentials['password'],
            );
        } catch (AccountLockedException|InvalidCredentialsException $exception) {
            throw ValidationException::withMessages([
                'username' => $exception instanceof AccountLockedException
                    ? 'Akun administrator dikunci sementara.'
                    : 'Username atau kata sandi administrator tidak sesuai.',
            ]);
        }

        Auth::guard('telescope')->login($administrator);
        $request->session()->regenerate();

        return redirect()->intended($this->dashboardPath());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('telescope')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('telescope.login');
    }

    private function dashboardPath(): string
    {
        return '/'.trim((string) config('telescope.path', 'telescope'), '/');
    }
}
