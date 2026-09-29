<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTelescope
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse
    {
        if (Auth::guard('telescope')->check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesi Telescope telah berakhir. Silakan masuk kembali.',
            ], 401);
        }

        return redirect()->guest(route('telescope.login'));
    }
}
