<?php

namespace Agency\Auth\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasClerkOrganization
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Users belonging to this organization
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('agency.auth.user_model'))
            ->using(config('agency.auth.organization_user_model'))
            ->withPivot('role', 'permissions', 'is_owner', 'title', 'status')
            ->withTimestamps();
    }

    /**
     * Add a user to the organization
     */
    public function addMember($user, array $attributes = []): void
    {
        $defaults = [
            'role' => null,
            'permissions' => [],
            'is_owner' => false,
            'title' => null,
            'status' => 'active'
        ];

        $attributes = array_merge($defaults, $attributes);
        $attributes['permissions'] = json_encode($attributes['permissions']);

        $this->users()->attach($user, $attributes);
    }

    /**
     * Remove a user from the organization
     */
    public function removeMember($user): void
    {
        $this->users()->detach($user);
    }

    /**
     * Get all active members
     */
    public function activeMembers(): Collection
    {
        return $this->users()->wherePivot('status', 'active')->get();
    }

    /**
     * Get all pending members
     */
    public function pendingMembers(): Collection
    {
        return $this->users()->wherePivot('status', 'pending')->get();
    }

    /**
     * Get organization owners
     */
    public function owners(): Collection
    {
        return $this->users()->wherePivot('is_owner', true)->get();
    }

    /**
     * Get members by role
     */
    public function getMembersByRole(string $role): Collection
    {
        return $this->users()->wherePivot('role', $role)->get();
    }

    /**
     * Update member attributes
     */
    public function updateMember($user, array $attributes): void
    {
        if (isset($attributes['permissions'])) {
            $attributes['permissions'] = json_encode($attributes['permissions']);
        }

        $this->users()->updateExistingPivot($user->id, $attributes);
    }

    /**
     * Get a member's complete data
     */
    public function getMember($user): ?object
    {
        return $this->users()->where('users.id', $user->id)->first()?->pivot;
    }

    /**
     * Get a member's role
     */
    public function getMemberRole($user): ?string
    {
        return $this->getMember($user)?->role;
    }

    /**
     * Get a member's permissions
     */
    public function getMemberPermissions($user): array
    {
        $permissions = $this->getMember($user)?->permissions;
        return json_decode($permissions ?? '[]', true);
    }

    /**
     * Check if user is an owner
     */
    public function isOwner($user): bool
    {
        return (bool) $this->getMember($user)?->is_owner;
    }

    /**
     * Check if user is an active member
     */
    public function isActiveMember($user): bool
    {
        $member = $this->getMember($user);
        return $member && $member->status === 'active';
    }

    /**
     * Check if user has permission
     */
    public function userHasPermission($user, string $permission): bool
    {
        $permissions = $this->getMemberPermissions($user);
        return in_array($permission, $permissions);
    }

    /**
     * Get member count by role
     */
    public function getMemberCountByRole(string $role): int
    {
        return $this->users()->wherePivot('role', $role)->count();
    }

    /**
     * Get total active members count
     */
    public function getActiveMemberCount(): int
    {
        return $this->users()->wherePivot('status', 'active')->count();
    }

    /**
     * Sync organization metadata with Clerk
     */
    public function syncWithClerk(array $clerkData): void
    {
        $this->clerk_metadata = array_merge(
            $this->clerk_metadata ?? [],
            $clerkData
        );
        $this->save();
    }

    /**
     * Get specific metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->clerk_metadata[$key] ?? $default;
    }
}