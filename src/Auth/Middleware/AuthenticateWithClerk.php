<?php

namespace Agency\Auth\Middleware;

use Closure;
use Agency\Auth\ClerkService;
use Agency\Auth\Exceptions\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithClerk
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
     * @param string|null $guard
     * @return mixed
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        if (!$this->clerk->verifySession()) {
            if ($request->expectsJson()) {
                throw new AuthenticationException('Unauthenticated');
            }
            
            return redirect()->route('login');
        }

        $user = $this->clerk->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('User not found');
        }

        // Store Clerk user data in request for later use
        $request->merge(['clerk_user' => $user]);

        return $next($request);
    }
}