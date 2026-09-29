<?php

namespace App\Http\Middleware;

use App\Models\MemberAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        $account?->loadMissing(['member.level', 'group']);

        if (! $account instanceof MemberAccount
            || $account->member?->member_status !== 1
            || ! $account->member?->level?->member_level_is_active
            || ! $account->group?->member_group_is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun member tidak aktif atau tidak memiliki akses.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
