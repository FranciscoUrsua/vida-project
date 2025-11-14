<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

class AuditReadMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Log read si es GET a modelo sensible (e.g., /api/social-users/{id})
        if ($request->method() === 'GET' && $request->routeIs('social-users.show') || $request->routeIs('social-users.index')) {
            Audit::create([
                'event' => 'read',
                'auditable_type' => $request->route('social_user') ? SocialUser::class : null, // Tipo entidad
                'auditable_id' => $request->route('social_user'), // ID si show
                'user_type' => get_class(Auth::user()),
                'user_id' => Auth::id(),
                'old_values' => null,
                'new_values' => null, // Opcional: Loggea data leída si needed
                'url' => $request->url(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }
}
