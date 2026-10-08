<?php

namespace Cultpantry\Elections\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ElectionsUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{articles: int, events: int, candidates: int, total: int, top: array<int, array{label: string, text: string, url: ?string}>, more?: int}  $digest
     */
    public function __construct(public array $digest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['articles' => $articles, 'events' => $events, 'candidates' => $candidates, 'total' => $total, 'top' => $top] = $this->digest;

        $parts = [];
        if ($articles > 0) {
            $parts[] = $articles.' new '.($articles === 1 ? 'news story' : 'news stories');
        }
        if ($events > 0) {
            $parts[] = $events.' new '.($events === 1 ? 'event' : 'events');
        }
        if ($candidates > 0) {
            $parts[] = 'new research on '.$candidates.($candidates === 1 ? ' candidate' : ' candidates');
        }

        $message = (new MailMessage)
            ->subject('Salmon Arm elections: '.implode(', ', $parts))
            ->greeting('New in the Elections tracker')
            ->line(ucfirst(implode(', ', $parts)).'.');

        foreach ($top as $item) {
            $text = $this->escape($item['text']);
            $message->line('- **'.$this->escape($item['label']).':** '.($item['url'] ? "[{$text}]({$item['url']})" : $text));
        }
        // Lines are grouped, so "more" counts remaining lines, not raw items.
        $more = $this->digest['more'] ?? max(0, $total - count($top));
        if ($more > 0) {
            $message->line('...and '.$more.' more.');
        }

        return $message
            ->action('View the new updates', route('admin.elections.index'))
            ->line('You receive this because you have access to the Elections section.');
    }

    private function escape(string $text): string
    {
        return addcslashes($text, '[]()*_`\\');
    }
}
