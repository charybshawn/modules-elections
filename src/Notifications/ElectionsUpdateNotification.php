<?php

namespace Cultpantry\Elections\Notifications;

use Cultpantry\Elections\Support\EmailPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The update email, for both elections, in the module's own template
 * (resources/views/mail) rather than the app's general mail theme: a header
 * with each election's voting-day countdown, then a section per election that
 * has something new. Plain and factual: it says what's new, not why anyone
 * should be excited about it.
 */
class ElectionsUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $email  see BuildElectionsEmail::handle()
     */
    public function __construct(public array $email) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->view(['elections::mail.update', 'elections::mail.update-text'], ['email' => $this->email, 'p' => new EmailPresenter]);
    }

    /** "Election update: 6 new for BC provincial, 8 for Salmon Arm" (nearest voting day first). */
    public function subject(): string
    {
        $short = ['provincial' => 'BC provincial', 'municipal' => 'Salmon Arm'];
        $parts = collect($this->email['sections'] ?? [])->map(fn (array $s) => $s['total'].' for '.$short[$s['key']])->all();

        return 'Election update: '.($parts === [] ? 'nothing new' : implode(', ', $parts));
    }
}
