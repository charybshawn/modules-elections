<?php

namespace Cultpantry\Elections\Http\Controllers\Admin;

use App\Actions\InviteUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Cultpantry\Elections\Http\Controllers\Admin\Concerns\ElectionsAdminMiddleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Str;

/**
 * "Invite someone" on the Elections landing page. Anyone who can open
 * Elections -- admins and invited viewers alike -- may invite an address, and
 * the invitation grants the Elections section only, read-only like their own.
 *
 * This is the one write invited viewers can make: the service provider lists
 * the route with AdminMiddleware::allowViewerWrite(), and the route is
 * throttled. Admins still manage broader access from Accounts.
 */
class ElectionInvitationController extends Controller implements HasMiddleware
{
    use ElectionsAdminMiddleware;

    public const SECTION = 'elections';

    public function store(Request $request, InviteUser $inviteUser): RedirectResponse
    {
        $inviter = $request->user();
        abort_unless($inviter->canAccessSection(self::SECTION), 403);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);
        $email = Str::lower(trim($validated['email']));

        // Refusals come back as errors on the email field, shown under the box.
        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withErrors(['email' => 'That address already has an account. Ask an admin to give it access to Elections.']);
        }

        // Re-inviting replaces a pending invitation, so never let this
        // Elections-only invite overwrite one an admin sent with more access.
        $pending = UserInvitation::pending()->where('email', $email)->first();
        if ($pending && $pending->permissions !== [self::SECTION]) {
            return back()->withErrors(['email' => 'That address already has a pending invitation from an admin.']);
        }

        try {
            $inviteUser->handle($email, [self::SECTION], $inviter);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('success', "Invitation sent to {$email}. They'll be able to view Elections only.");
    }
}
