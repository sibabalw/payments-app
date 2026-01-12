<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Business $business): bool
    {
        return $user->businesses()->where('businesses.id', $business->id)->exists()
            || $user->ownedBusinesses()->where('id', $business->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Business $business): bool
    {
        // Owner or manager can update
        return $user->businesses()
            ->where('businesses.id', $business->id)
            ->whereIn('business_user.role', ['owner', 'manager'])
            ->exists()
            || $user->ownedBusinesses()->where('id', $business->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Business $business): bool
    {
        // Only owner can delete
        return $user->businesses()
            ->where('businesses.id', $business->id)
            ->where('business_user.role', 'owner')
            ->exists()
            || $user->ownedBusinesses()->where('id', $business->id)->exists();
    }
}
