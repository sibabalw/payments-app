<?php

namespace App\Policies;

use App\Models\Receiver;
use App\Models\User;

class ReceiverPolicy
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
    public function view(User $user, Receiver $receiver): bool
    {
        return $user->businesses()
            ->where('businesses.id', $receiver->business_id)
            ->exists();
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
    public function update(User $user, Receiver $receiver): bool
    {
        return $user->businesses()
            ->where('businesses.id', $receiver->business_id)
            ->whereIn('business_user.role', ['owner', 'manager'])
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Receiver $receiver): bool
    {
        return $user->businesses()
            ->where('businesses.id', $receiver->business_id)
            ->whereIn('business_user.role', ['owner', 'manager'])
            ->exists();
    }
}
