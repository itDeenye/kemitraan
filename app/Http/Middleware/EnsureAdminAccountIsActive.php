<?php

namespace App\Http\Middleware;

use App\Models\SiteAdministrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $administrator = $request->user();

        if (! $administrator instanceof SiteAdministrator
            || ! $administrator->administrator_is_active
            || ! $administrator->group?->administrator_group_is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun administrator tidak aktif atau tidak memiliki akses.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
