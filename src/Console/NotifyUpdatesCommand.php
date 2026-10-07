<?php

namespace Cultpantry\Elections\Console;

use App\Models\User;
use Cultpantry\Elections\Actions\BuildUpdateDigest;
use Cultpantry\Elections\Models\UpdateDigest;
use Cultpantry\Elections\Notifications\ElectionsUpdateNotification;
use Cultpantry\Elections\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Emails everyone with access to the Elections section one digest of what
 * was added since the last run (new articles, events and candidate research),
 * so a big import is one email, not one per item. Runs hourly after the
 * import; sends nothing when nothing is new. The first run only records a
 * baseline, so turning this on doesn't mail everything already on file.
 */
class NotifyUpdatesCommand extends Command
{
    protected $signature = 'elections:notify-updates
        {--dry-run : Report what would be sent and to whom, without sending or recording anything}';

    protected $description = 'Email users with Elections access a digest of new articles and research';

    public function handle(BuildUpdateDigest $build): int
    {
        $until = now();
        $last = UpdateDigest::query()->latest('covers_through')->first();
        $dryRun = (bool) $this->option('dry-run');

        if ($last === null) {
            if (! $dryRun) {
                UpdateDigest::create(['covers_through' => $until, 'recipients' => 0]);
            }
            $this->line('First run: recorded a baseline; nothing sent.');

            return self::SUCCESS;
        }

        $digest = $build->handle($last->covers_through, $until);
        if ($digest['total'] === 0) {
            $this->line('Nothing new.');

            return self::SUCCESS;
        }

        $recipients = static::recipients();
        $this->line("{$digest['total']} new item(s); ".$recipients->count().' recipient(s).');

        if ($dryRun) {
            $recipients->each(fn (User $u) => $this->line("  would email {$u->email}"));

            return self::SUCCESS;
        }

        Notification::send($recipients, new ElectionsUpdateNotification($digest));
        Audit::record('elections.update_digest_sent', "Elections update email sent to {$recipients->count()} recipient(s)", null, [
            'items' => $digest['total'],
            'recipients' => $recipients->pluck('email')->all(),
        ]);
        UpdateDigest::create([
            'covers_through' => $until,
            'counts' => [
                'articles' => $digest['articles'],
                'events' => $digest['events'],
                'candidates' => $digest['candidates'],
            ],
            'recipients' => $recipients->count(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Active, verified users who can open the Elections section: admins with
     * no section list, and anyone granted it (including invited viewers).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function recipients(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->where(fn ($q) => $q->whereIn('role', ['admin', 'super_admin'])->orWhereNotNull('admin_permissions'))
            ->get()
            ->filter(fn (User $u) => $u->canAccessAdminPanel() && $u->canAccessSection('elections'))
            ->values();
    }
}
