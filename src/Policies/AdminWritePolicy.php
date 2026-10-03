<?php

namespace Cultpantry\Elections\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-only for every ability. Invited read-only viewers (panel users
 * granted the Elections section) still pass view/viewAny: the host's
 * Gate::before allows exactly those two abilities, and only inside a request
 * AdminMiddleware has vetted for their section. Writes never reach them --
 * AdminMiddleware also refuses non-safe methods for non-admins.
 *
 * Registered for Entry, Article and ElectionEvent; CandidatePolicy extends it.
 */
class AdminWritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin();
    }
}
