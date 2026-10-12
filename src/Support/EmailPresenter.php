<?php

namespace Cultpantry\Elections\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Small helpers for the update email's templates: safe HTML from the digest's
 * light markup, party colours, dates. Everything that comes from the database
 * is escaped here, so the templates never print raw text.
 */
class EmailPresenter
{
    /** Party colours as they appear on the site (hex, since email can't use the site's CSS). */
    private const COLOURS = ['ndp' => '#ea580c', 'conservative' => '#1d4ed8', 'green' => '#16a34a', 'centre' => '#0d9488', 'onebc' => '#be123c'];

    private const SHORT = ['ndp' => 'NDP', 'conservative' => 'CON', 'green' => 'GRN', 'centre' => 'CTR', 'onebc' => 'ONE'];

    private const RATING_COLOURS = [
        'holds_up' => ['#065f46', '#d1fae5'],
        'mostly_holds_up' => ['#3f6212', '#ecfccb'],
        'mixed' => ['#92400e', '#fef3c7'],
        'mostly_does_not' => ['#9a3412', '#ffedd5'],
        'does_not_hold_up' => ['#9f1239', '#ffe4e6'],
        'untestable' => ['#334155', '#e2e8f0'],
    ];

    private function key(string $party): ?string
    {
        $k = Str::of($party)->lower()->replaceMatches('/[^a-z]/', '')->toString();

        return match (true) {
            str_contains($k, 'ndp') || str_contains($k, 'newdemocrat') => 'ndp',
            str_contains($k, 'conservative') => 'conservative',
            str_contains($k, 'green') => 'green',
            str_contains($k, 'centre') || str_contains($k, 'center') => 'centre',
            $k === 'onebc' || $k === 'one' => 'onebc',
            default => null,
        };
    }

    public function colour(string $party): string
    {
        return self::COLOURS[$this->key($party)] ?? '#6b7280';
    }

    public function short(string $party): string
    {
        return self::SHORT[$this->key($party)] ?? strtoupper(Str::limit($party, 3, ''));
    }

    /** @return array{0: string, 1: string} text and background colours for a rating badge */
    public function ratingColours(string $rating): array
    {
        return self::RATING_COLOURS[$rating] ?? ['#334155', '#e2e8f0'];
    }

    /** Escaped text with the digest's **bold** turned into <strong>. */
    public function bold(?string $text): HtmlString
    {
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e((string) $text));

        return new HtmlString($html);
    }

    /** The same text with the bold markers removed, for the plain-text email. */
    public function plain(?string $text): string
    {
        return str_replace('**', '', (string) $text);
    }

    /** Escaped text where each name that has an address becomes a link. */
    public function linkNames(?string $text, array $links = []): HtmlString
    {
        $html = e((string) $text);
        foreach ($links as $name => $url) {
            $escaped = e($name);
            $pos = strpos($html, $escaped);
            if ($pos !== false && is_string($url) && preg_match('#^https?://#i', $url)) {
                $html = substr_replace($html, '<a href="'.e($url).'" style="color:#4338ca;">'.$escaped.'</a>', $pos, strlen($escaped));
            }
        }

        return new HtmlString($html);
    }

    public function date(?string $iso, string $format = 'D, M j'): string
    {
        return $iso ? Carbon::parse($iso)->timezone(config('app.timezone'))->format($format) : '';
    }

    /** "6 days", "tomorrow", "today", or "voted" once it has passed. */
    public function countdown(?int $days): string
    {
        return match (true) {
            $days === null => '',
            $days < 0 => 'voting is over',
            $days === 0 => 'today',
            $days === 1 => 'tomorrow',
            default => $days.' days away',
        };
    }

    /** A signed one-decimal change, with a real minus sign; empty when unknown. */
    public function change(?float $change): string
    {
        if ($change === null) {
            return '';
        }

        return abs($change) < 0.05 ? '±0.0' : ($change > 0 ? '+' : '−').number_format(abs($change), 1);
    }

    public function safeUrl(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? $url : null;
    }

    public function join(array $items): string
    {
        return count($items) > 1 ? implode(', ', array_slice($items, 0, -1)).' and '.end($items) : (string) ($items[0] ?? '');
    }
}
