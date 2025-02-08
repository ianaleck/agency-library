<?php

namespace Agency\Auth\Contracts;

interface AuthServiceInterface
{
    /**
     * Get the currently authenticated user
     */
    public function getCurrentUser(): ?array;

    /**
     * Verify the current session
     */
    public function verifySession(): bool;

    /**
     * Get a user by their ID
     */
    public function getUser(string $userId): ?array;

    /**
     * Validate a session token
     */
    public function validateToken(string $token): array;

    /**
     * Check if user has specific permissions
     */
    public function checkPermissions(array|string $permissions): bool;
}