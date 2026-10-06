<?php

namespace Cultpantry\Elections\Console;

use Cultpantry\Elections\Models\Candidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cross-references the candidates' names against the public pages of local
 * organizations (churches, service clubs, societies, parties ...) listed in
 * data/organizations.json. The registry holds organizations only -- never
 * people. Each organization's own pages (leadership, board, staff, member
 * lists, newsletters it publishes) are fetched and their text cached in the
 * host app's storage/app/elections/org-cache, which is not committed; a
 * cached page is reused until it is older than --max-age days. Hits are
 * leads to verify against the page, not facts: common names collide.
 */
class ScanOrganizationsCommand extends Command
{
    protected $signature = 'elections:scan-organizations
        {--candidate= : Only this candidate (exact name on file)}
        {--org= : Only organizations whose name or slug contains this}
        {--refresh : Re-fetch pages even when cached}
        {--max-age=14 : Days a cached page is reused}
        {--pages=25 : Most pages to fetch per organization}
        {--timeout=60 : Most seconds to spend crawling one organization}
        {--purge : Delete the page cache and stop}
        {--json : Print hits as JSON}';

    protected $description = 'Search local organizations\' public pages for the candidates\' names';

    private const ROSTER_LINK = '/about|leader|staff|team|elder|pastor|people|board|director|executive|council|member|who-we|meet|committee|volunteer|newsletter|bulletin|news|minutes/i';

    public function handle(): int
    {
        $cache = storage_path('app/elections/org-cache');
        if ($this->option('purge')) {
            File::deleteDirectory($cache);
            $this->line('Cache purged.');

            return self::SUCCESS;
        }

        $registry = json_decode((string) file_get_contents(static::registryPath()), true) ?: [];
        $orgs = collect($registry['organizations'] ?? [])
            ->filter(fn ($o) => ! $this->option('org') || Str::contains(Str::lower($o['name'].' '.($o['slug'] ?? '')), Str::lower($this->option('org'))))
            ->values();

        $candidates = Candidate::query()
            ->when($this->option('candidate'), fn ($q, $name) => $q->where('name', $name))
            ->pluck('name');
        if ($candidates->isEmpty()) {
            $this->error('No matching candidates on file.');

            return self::FAILURE;
        }

        $hits = [];
        $unreachable = [];
        foreach ($orgs as $org) {
            $slug = $org['slug'] ?? Str::slug($org['name']);
            $pages = $this->pagesFor($org, $slug, $cache);
            if ($pages === []) {
                $unreachable[] = $org['name'];

                continue;
            }
            foreach ($pages as $url => $text) {
                foreach ($candidates as $name) {
                    foreach ($this->matches($name, $text) as $snippet) {
                        $hits[] = ['candidate' => $name, 'organization' => $org['name'], 'type' => $org['type'] ?? null, 'url' => $url, 'snippet' => $snippet];
                    }
                }
            }
        }

        $hits = collect($hits)->unique(fn ($h) => $h['candidate'].'|'.$h['url'])->values();

        if ($this->option('json')) {
            $this->line($hits->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line($orgs->count().' organizations scanned for '.$candidates->count().' candidate(s); '.count($unreachable).' unreachable.');
        foreach ($hits->groupBy('candidate') as $name => $group) {
            $this->line('');
            $this->line("<options=bold>{$name}</>");
            foreach ($group as $h) {
                $this->line("  {$h['organization']} ({$h['type']}): {$h['url']}");
                $this->line('    ...'.$h['snippet'].'...');
            }
        }
        if ($unreachable !== []) {
            $this->line('');
            $this->warn('Could not fetch: '.implode(', ', $unreachable));
        }
        $this->line('');
        $this->line('Hits are leads: open the page and confirm it is this candidate before recording anything.');

        return self::SUCCESS;
    }

    public static function registryPath(): string
    {
        return dirname(__DIR__, 2).'/data/organizations.json';
    }

    /**
     * Page text by URL for one organization, from cache when fresh.
     *
     * @return array<string, string>
     */
    private function pagesFor(array $org, string $slug, string $cache): array
    {
        $file = "{$cache}/{$slug}.json";
        $maxAge = (int) $this->option('max-age') * 86400;
        if (! $this->option('refresh') && is_file($file) && time() - filemtime($file) < $maxAge) {
            return json_decode((string) file_get_contents($file), true) ?: [];
        }

        $failed = "{$cache}/{$slug}.failed";
        if (! $this->option('refresh') && is_file($failed) && time() - filemtime($failed) < 86400) {
            return [];
        }

        $pages = $this->crawl($org);
        File::ensureDirectoryExists($cache);
        if ($pages !== []) {
            file_put_contents($file, json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            touch($failed);
        }

        return $pages;
    }

    /**
     * The organization's own site: its listed pages first, then links that
     * look like rosters, then the rest, same host only.
     *
     * @return array<string, string>
     */
    private function crawl(array $org): array
    {
        $start = array_values(array_unique([...($org['pages'] ?? []), $org['url']]));
        $host = $this->host($org['url']);
        $queue = $start;
        $seen = array_flip($start);
        $pages = [];
        $limit = (int) $this->option('pages');
        $deadline = time() + (int) $this->option('timeout');

        while ($queue !== [] && count($pages) < $limit && time() < $deadline) {
            $batch = array_splice($queue, 0, min(6, $limit - count($pages)));
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($u) => $pool->as($u)->withUserAgent('Mozilla/5.0 (compatible; ElectionsResearch/1.0)')->connectTimeout(5)->timeout(8)->get($u),
                $batch,
            ));

            foreach ($batch as $url) {
                $r = $responses[$url] ?? null;
                if (! $r instanceof \Illuminate\Http\Client\Response || ! $r->ok() || ! str_contains((string) $r->header('Content-Type'), 'html')) {
                    continue;
                }
                $html = $r->body();
                $pages[$url] = $this->text($html);

                preg_match_all('/href="([^"#]+)"/i', $html, $m);
                foreach ($m[1] as $href) {
                    $link = $this->absolute($url, html_entity_decode($href));
                    if ($link === null || isset($seen[$link]) || $this->host($link) !== $host || preg_match('/\.(jpe?g|png|gif|svg|css|js|pdf|mp3|mp4|zip)(\?|$)/i', $link)) {
                        continue;
                    }
                    $seen[$link] = true;
                    preg_match(self::ROSTER_LINK, $link) ? array_unshift($queue, $link) : $queue[] = $link;
                }
            }
        }

        return $pages;
    }

    /**
     * Snippets where the candidate is named: the full name, "Last, First", or
     * the surname with the first name within 60 characters.
     *
     * @return array<int, string>
     */
    private function matches(string $name, string $text): array
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = preg_quote(Str::lower($parts[0]), '/');
        $last = preg_quote(Str::lower(end($parts)), '/');
        $lower = Str::lower($text);

        $patterns = [
            '/\b'.$first.'\s+(?:[a-z.\'-]+\s+){0,3}'.$last.'\b/u',
            '/\b'.$last.',\s*'.$first.'\b/u',
            '/\b'.$first.'\b.{0,60}\b'.$last.'\b/us',
            '/\b'.$last.'\b.{0,60}\b'.$first.'\b/us',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower, $m, PREG_OFFSET_CAPTURE)) {
                $start = max(0, $m[0][1] - 100);

                return [trim(mb_substr($text, $start, mb_strlen($m[0][0]) + 220))];
            }
        }

        return [];
    }

    private function text(string $html): string
    {
        $html = preg_replace('#<(script|style|svg|noscript)\b.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<[^>]+>#', ' ', $html) ?? $html;

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($html, ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    private function host(string $url): string
    {
        return Str::lower(preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST)));
    }

    private function absolute(string $base, string $href): ?string
    {
        if (preg_match('#^(mailto:|tel:|javascript:)#i', $href)) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $origin = $p['scheme'].'://'.$p['host'];
        if (str_starts_with($href, '//')) {
            return $p['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/');

        return $origin.$dir.$href;
    }
}
