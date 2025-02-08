<?php

namespace Agency\Auth\Middleware;

use Closure;
use Agency\Auth\ClerkService;
use Agency\Auth\Exceptions\AuthenticationException;
use Illuminate\Http\Request;

class CheckPermissions
{
    private ClerkService $clerk;

    public function __construct(ClerkService $clerk)
    {
        $this->clerk = $clerk;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|array $permissions
     * @return mixed
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        if (!$this->clerk->checkPermissions($permissions)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}