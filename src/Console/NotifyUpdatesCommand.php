<?php

namespace Cultpantry\Elections\Console;

use App\Models\User;
use Cultpantry\Elections\Actions\BuildElectionsEmail;
use Cultpantry\Elections\Models\UpdateDigest;
use Cultpantry\Elections\Notifications\ElectionsUpdateNotification;
use Cultpantry\Elections\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Emails everyone with access to the Elections section one digest of what
 * was added since the last run, for both elections (news, events, candidate
 * research, and for the provincial one the polls, fact-checks and party news)
 * plus the site's change log entries whose commits fall in the same period,
 * so a big import is one email, not one per item. Runs hourly after the
 * import; sends nothing when nothing is new. The first run only records a
 * baseline, so turning this on doesn't mail everything already on file.
 *
 * --preview=FILE writes the email as HTML to FILE (nothing is sent or recorded),
 * for a look at the design; --since="2 days ago" widens the period for that or
 * for --dry-run.
 */
class NotifyUpdatesCommand extends Command
{
    protected $signature = 'elections:notify-updates
        {--dry-run : Report what would be sent and to whom, without sending or recording anything}
        {--preview= : Write the email as HTML to this file instead of sending it (nothing is recorded)}
        {--since= : Cover everything since this time (e.g. "2 days ago") instead of since the last email; for --dry-run and --preview}';

    protected $description = 'Email users with Elections access a digest of new articles and research';

    public function handle(BuildElectionsEmail $build): int
    {
        $until = now();
        $last = UpdateDigest::query()->latest('covers_through')->first();
        $preview = $this->option('preview');
        $dryRun = (bool) $this->option('dry-run') || $preview;

        $since = $last?->covers_through;
        if ($this->option('since')) {
            if (! $dryRun) {
                $this->error('--since only works with --dry-run or --preview.');

                return self::FAILURE;
            }
            $since = now()->parse($this->option('since'));
        }

        if ($since === null) {
            if (! $dryRun) {
                UpdateDigest::create(['covers_through' => $until, 'recipients' => 0]);
            }
            $this->line('First run: recorded a baseline; nothing sent.');

            return self::SUCCESS;
        }

        $digest = $build->handle($since, $until);
        if ($digest['total'] === 0) {
            $this->line('Nothing new.');

            return self::SUCCESS;
        }

        $notification = new ElectionsUpdateNotification($digest);
        $recipients = static::recipients();
        $this->line("{$digest['total']} new item(s); ".$recipients->count().' recipient(s).');
        $this->line('Subject: '.$notification->subject());

        if ($preview) {
            file_put_contents($preview, $notification->toMail(new User)->render());
            $this->line("Wrote the email to {$preview}.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $recipients->each(fn (User $u) => $this->line("  would email {$u->email}"));

            return self::SUCCESS;
        }

        Notification::send($recipients, $notification);
        Audit::record('elections.update_digest_sent', "Elections update email sent to {$recipients->count()} recipient(s)", null, [
            'items' => $digest['total'],
            'sections' => collect($digest['sections'])->pluck('key')->all(),
            'recipients' => $recipients->pluck('email')->all(),
        ]);
        UpdateDigest::create([
            'covers_through' => $until,
            'counts' => collect($digest['sections'])->mapWithKeys(fn (array $s) => [$s['key'] => $s['total']])->put('changelog', count($digest['changelog']))->all(),
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
