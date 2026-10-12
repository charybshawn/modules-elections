{{-- The headline digest for one election: news, a question many answered, what's coming, candidate highlights. --}}
@php use Illuminate\Support\Str; @endphp

@if ($d['news'] !== [])
    <tr><td class="pad" style="padding:14px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:6px;">In the news</div>
        @foreach ($d['news'] as $story)
            <div style="padding:6px 0;{{ ! $loop->last || $d['more_news'] > 0 ? 'border-bottom:1px solid #1f2937;' : '' }}">
                @if ($p->safeUrl($story['url']))
                    <a href="{{ $story['url'] }}" style="font-size:15px;font-weight:600;color:#f3f4f6;text-decoration:none;">{{ $story['title'] }}</a>
                @else
                    <span style="font-size:15px;font-weight:600;color:#f3f4f6;">{{ $story['title'] }}</span>
                @endif
                @if (! empty($story['outlet']))<span style="font-size:12px;color:#9ca3af;"> · {{ $story['outlet'] }}</span>@endif
                @if ($story['text'] !== '')<div style="font-size:14px;line-height:1.5;color:#9ca3af;">{{ $p->linkNames($story['text'], $story['links'] ?? []) }}</div>@endif
            </div>
        @endforeach
        @if ($d['more_news'] > 0)<div style="font-size:13px;color:#9ca3af;padding-top:6px;">Plus {{ $d['more_news'] }} more {{ Str::plural('story', $d['more_news']) }} in the tracker.</div>@endif
    </td></tr>
@endif

@foreach ($d['shared'] as $item)
    <tr><td class="pad" style="padding:10px 20px 4px 20px;">
        <div style="background:#1f2937;border-left:3px solid {{ $color }};padding:10px 14px;border-radius:0 6px 6px 0;">
            <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;">One question, many answers</div>
            <div style="padding-top:3px;">
                @if ($p->safeUrl($item['url']))<a href="{{ $item['url'] }}" style="font-size:15px;font-weight:600;color:#f3f4f6;text-decoration:none;">{{ $item['title'] }}</a>@else<span style="font-size:15px;font-weight:600;color:#f3f4f6;">{{ $item['title'] }}</span>@endif
                @if ($item['outlet'])<span style="font-size:12px;color:#9ca3af;"> · {{ $item['outlet'] }}</span>@endif
            </div>
            <div style="font-size:14px;line-height:1.5;color:#9ca3af;">{{ $item['text'] }}</div>
        </div>
    </td></tr>
@endforeach

@if ($d['coming_up'] !== [])
    <tr><td class="pad" style="padding:14px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:4px;">Coming up</div>
        @foreach ($d['coming_up'] as $line)<div style="font-size:14px;line-height:1.6;color:#d1d5db;">&bull; {{ $line }}</div>@endforeach
    </td></tr>
@endif

@if ($d['highlights'] !== [])
    <tr><td class="pad" style="padding:14px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:4px;">From the candidates</div>
        @foreach ($d['highlights'] as $sentence)<div style="font-size:14px;line-height:1.55;color:#d1d5db;padding:3px 0;">{{ $p->bold($sentence) }}</div>@endforeach
    </td></tr>
@endif

@if ($d['also'] !== [])
    <tr><td class="pad" style="padding:10px 20px 4px 20px;">
        <div style="font-size:13px;line-height:1.5;color:#9ca3af;"><strong style="color:#d1d5db;">Also updated:</strong> {{ $p->join($d['also']) }}.</div>
    </td></tr>
@endif
