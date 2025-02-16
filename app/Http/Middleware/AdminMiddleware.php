<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->roles->where('name', 'admin')->isNotEmpty()) {
            return $next($request);
        }

        return response()->json([
            'code'    => 403,
            'message' => 'Access denied. Admins only.',
        ], 403);
    }
}