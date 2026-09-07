<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Staff role required.',
            ], 403);
        }

        if (Roles::isSuperAdmin($user->role)) {
            return $next($request);
        }

        $allowed = $roles !== [] ? $roles : Roles::staff();

        if (! in_array($user->role, $allowed, true)) {
            return response()->json([
                'message' => 'Staff role required.',
            ], 403);
        }

        return $next($request);
    }
}
