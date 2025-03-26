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

        if ($user && $user->roles->whereIn('name', ['admin', 'super admin'])->isNotEmpty()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'code'    => 403,
                'message' => 'Access denied. Admins only.',
            ], 403);
        }

        return redirect()->route('admin.login')->with('error', 'Access denied. Admins only.');
    }
}
