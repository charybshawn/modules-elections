<?php

namespace Cultpantry\Elections\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The update email, laid out as a brief newsletter of headlines: the news,
 * any questionnaire many candidates answered, what's coming up, a sentence
 * each from a few candidates, and who else has new research. Plain and
 * factual: it says what's new, not why anyone should be excited about it.
 */
class ElectionsUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $digest  see BuildUpdateDigest::handle()
     */
    public function __construct(public array $digest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest + ['news' => [], 'more_news' => 0, 'coming_up' => [], 'shared' => [], 'highlights' => [], 'also' => []];

        $parts = [];
        if ($d['articles'] > 0) {
            $parts[] = $d['articles'].' new '.($d['articles'] === 1 ? 'news story' : 'news stories');
        }
        if ($d['events'] > 0) {
            $parts[] = $d['events'].' new '.($d['events'] === 1 ? 'event' : 'events');
        }
        if ($d['candidates'] > 0) {
            $parts[] = 'new research on '.$d['candidates'].($d['candidates'] === 1 ? ' candidate' : ' candidates');
        }

        $message = (new MailMessage)
            ->subject('Salmon Arm elections: '.implode(', ', $parts))
            ->greeting('New in the Salmon Arm election tracker')
            ->line('This update covers '.$this->join($parts).'.');

        if ($d['news'] !== []) {
            $message->line('**In the news**');
            foreach ($d['news'] as $story) {
                $title = $this->escape($story['title']);
                $outlet = ! empty($story['outlet']) ? ' ('.$this->escape($story['outlet']).')' : '';
                $text = $story['text'] !== '' ? ' — '.$this->linkNames($this->escape($story['text']), $story['links'] ?? []) : '';
                $message->line(($story['url'] ? "[{$title}]({$story['url']})" : "**{$title}**").$outlet.$text);
            }
            if ($d['more_news'] > 0) {
                $message->line('Plus '.$d['more_news'].' more '.($d['more_news'] === 1 ? 'story' : 'stories').' in the tracker.');
            }
        }

        foreach ($d['shared'] as $item) {
            $title = $this->escape($item['title']);
            $link = $item['url'] ? "[{$title}]({$item['url']})" : $title;
            $message->line('**One question, many answers:** '.$link.($item['outlet'] ? ' ('.$this->escape($item['outlet']).')' : '').'. '.$this->escape($item['text']));
        }

        if ($d['coming_up'] !== []) {
            $message->line('**Coming up:** '.$this->escape(implode('; ', $d['coming_up'])).'.');
        }

        if ($d['highlights'] !== []) {
            $message->line('**From the candidates**');
            foreach ($d['highlights'] as $sentence) {
                $message->line($this->escapeKeepingBold($sentence));
            }
        }

        if ($d['also'] !== []) {
            $message->line('**Also updated:** '.$this->escape($this->join($d['also'])).'.');
        }

        return $message
            ->action('Read the updates', route('admin.elections.index'))
            ->line('You receive this because you have access to the Elections section.');
    }

    /** Turns each candidate's name in a series item into a link to their story. */
    private function linkNames(string $text, array $links): string
    {
        foreach ($links as $name => $url) {
            $escaped = $this->escape($name);
            $pos = strpos($text, $escaped);
            if ($pos !== false && $url) {
                $text = substr_replace($text, "[{$escaped}]({$url})", $pos, strlen($escaped));
            }
        }

        return $text;
    }

    private function join(array $items): string
    {
        return count($items) > 1 ? implode(', ', array_slice($items, 0, -1)).' and '.end($items) : (string) ($items[0] ?? '');
    }

    private function escape(string $text): string
    {
        return addcslashes($text, '[]()*_`\\');
    }

    /** Escapes Markdown except the **bold** the digest puts around a candidate's name. */
    private function escapeKeepingBold(string $text): string
    {
        return preg_replace_callback('/\*\*(.+?)\*\*|([^*]+|\*)/s', fn ($m) => $m[1] !== '' ? '**'.$this->escape($m[1]).'**' : $this->escape($m[0]), $text);
    }
}
