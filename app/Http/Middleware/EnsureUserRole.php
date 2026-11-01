<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(
        Request $request,
        Closure $next,
        string $role
    ): Response {
        $user = $request->user();

        if (
            !$user ||
            $user->role !== $role ||
            !$user->tokenCan($role)
        ) {
            return response()->json([
                'message' => 'You are not authorized to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}