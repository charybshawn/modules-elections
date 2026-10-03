<?php

namespace Cultpantry\Elections\Policies;

use App\Models\User;

class CandidatePolicy extends AdminWritePolicy
{
    /**
     * Class-level ability for the XML import, which doesn't act on one
     * existing candidate.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admin-only, even though it's a GET: the export is the whole dataset
     * including research notes, not something to hand to a read-only viewer.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }
}
