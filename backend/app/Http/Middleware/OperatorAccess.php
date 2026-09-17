<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OperatorAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'super_admin', 'officer'], true), 403, 'Akun ini tidak memiliki akses operasional.');

        return $next($request);
    }
}
