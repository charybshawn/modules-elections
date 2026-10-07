<?php

use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;

describe('elections invitations', function () {
    beforeEach(function () {
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->viewer = User::factory()->create(['role' => 'customer']);
        $this->viewer->forceFill(['admin_permissions' => ['elections']])->save();
        $this->viewer = $this->viewer->fresh();
    });

    it('lets an admin invite someone to Elections only', function () {
        $this->actingAs($this->admin)
            ->from('/admin/elections')
            ->post(route('admin.elections.invitations.store'), ['email' => 'New@Example.com'])
            ->assertRedirect('/admin/elections')
            ->assertSessionHas('success');

        $invitation = UserInvitation::where('email', 'new@example.com')->sole();
        expect($invitation->permissions)->toBe(['elections'])
            ->and($invitation->invited_by)->toBe($this->admin->id);
        Notification::assertSentOnDemand(UserInvitationNotification::class);
    });

    it('lets an invited viewer invite someone, despite being read-only elsewhere', function () {
        $this->actingAs($this->viewer)
            ->from('/admin/elections')
            ->post(route('admin.elections.invitations.store'), ['email' => 'friend@example.com'])
            ->assertRedirect('/admin/elections')
            ->assertSessionHas('success');

        expect(UserInvitation::where('email', 'friend@example.com')->sole()->permissions)->toBe(['elections']);
    });

    it('keeps every other write refused for viewers', function () {
        $this->actingAs($this->viewer)
            ->get(route('admin.elections.export'))
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->post(route('admin.elections.import'))
            ->assertForbidden();
    });

    it('refuses customers without Elections access', function () {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->post(route('admin.elections.invitations.store'), ['email' => 'x@example.com'])
            ->assertForbidden();

        expect(UserInvitation::count())->toBe(0);
    });

    it('refuses an address that already has an account', function () {
        $this->actingAs($this->viewer)
            ->from('/admin/elections')
            ->post(route('admin.elections.invitations.store'), ['email' => $this->admin->email])
            ->assertSessionHasErrors('email');

        expect(UserInvitation::count())->toBe(0);
    });

    it('never replaces a pending admin invitation that grants more', function () {
        UserInvitation::create([
            'email' => 'staff@example.com',
            'token_hash' => UserInvitation::hashToken('x'),
            'permissions' => ['elections', 'orders'],
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($this->viewer)
            ->from('/admin/elections')
            ->post(route('admin.elections.invitations.store'), ['email' => 'staff@example.com'])
            ->assertSessionHasErrors('email');

        expect(UserInvitation::where('email', 'staff@example.com')->sole()->permissions)->toBe(['elections', 'orders']);
    });

    it('validates the address', function () {
        $this->actingAs($this->viewer)
            ->from('/admin/elections')
            ->post(route('admin.elections.invitations.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    });

    it('throttles a flood of invitations', function () {
        foreach (range(1, 10) as $i) {
            $this->actingAs($this->viewer)->post(route('admin.elections.invitations.store'), ['email' => "p{$i}@example.com"]);
        }

        $this->actingAs($this->viewer)
            ->post(route('admin.elections.invitations.store'), ['email' => 'p11@example.com'])
            ->assertStatus(429);
    });
});
