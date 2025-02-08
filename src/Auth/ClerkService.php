<?php

namespace Agency\Auth;

use Agency\Auth\Contracts\AuthServiceInterface;
use Agency\Auth\Exceptions\AuthenticationException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ClerkService implements AuthServiceInterface
{
    private HttpClient $client;
    private string $secretKey;
    private string $publishableKey;
    private ?array $currentUser = null;

    public function __construct(string $secretKey, string $publishableKey)
    {
        $this->secretKey = $secretKey;
        $this->publishableKey = $publishableKey;
        $this->client = new HttpClient([
            'base_uri' => config('agency.auth.clerk.backend_api'),
            'headers' => [
                'Authorization' => "Bearer {$this->secretKey}",
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Make an API request to Clerk
     */
    private function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException(
                "Clerk API request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the current session token from cookie
     */
    private function getSessionToken(): ?string
    {
        return request()->cookie(config('agency.auth.clerk.session_token_cookie'));
    }

    /**
     * Get the currently authenticated user
     */
    public function getCurrentUser(): ?array
    {
        if ($this->currentUser) {
            return $this->currentUser;
        }

        $token = $this->getSessionToken();
        if (!$token) {
            return null;
        }

        try {
            $session = $this->validateToken($token);
            $userId = $session['user_id'] ?? null;
            
            if (!$userId) {
                return null;
            }

            $this->currentUser = $this->getUser($userId);
            return $this->currentUser;
        } catch (AuthenticationException $e) {
            return null;
        }
    }

    /**
     * Verify the current session
     */
    public function verifySession(): bool
    {
        $token = $this->getSessionToken();
        if (!$token) {
            return false;
        }

        try {
            $this->validateToken($token);
            return true;
        } catch (AuthenticationException $e) {
            return false;
        }
    }

    /**
     * Get a user by their ID
     */
    public function getUser(string $userId): ?array
    {
        $cacheKey = "clerk_user_{$userId}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($userId) {
            try {
                return $this->request('GET', "users/{$userId}");
            } catch (AuthenticationException $e) {
                return null;
            }
        });
    }

    /**
     * Validate a session token
     */
    public function validateToken(string $token): array
    {
        return $this->request('POST', 'tokens/verify', [
            'json' => ['token' => $token]
        ]);
    }

    /**
     * Check if user has specific permissions
     */
    public function checkPermissions(array|string $permissions): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        // Convert single permission to array
        $permissions = (array) $permissions;

        // Get user's permissions from metadata or public metadata
        $userPermissions = $user['public_metadata']['permissions'] ?? [];

        // Check if user has all required permissions
        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }

        return true;
    }
}