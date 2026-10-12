{{-- An election with nothing new since the last email: what is coming up and its latest stories. --}}
<tr><td class="pad" style="padding:6px 20px 4px 20px;">
    <div style="font-size:14px;line-height:1.5;color:#9ca3af;">No new updates since the last email.</div>
</td></tr>

@if ($q['stories'] !== [])
    <tr><td class="pad" style="padding:10px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:6px;">Latest stories</div>
        @foreach ($q['stories'] as $story)
            <div style="padding:5px 0;{{ ! $loop->last ? 'border-bottom:1px solid #1f2937;' : '' }}">
                @if ($p->safeUrl($story['url']))<a href="{{ $story['url'] }}" style="font-size:14px;font-weight:600;color:#f3f4f6;text-decoration:none;">{{ $story['title'] }}</a>@else<span style="font-size:14px;font-weight:600;color:#f3f4f6;">{{ $story['title'] }}</span>@endif
                <span style="font-size:12px;color:#9ca3af;"> · {{ collect([$story['outlet'], $p->date($story['published_on'], 'M j')])->filter()->implode(' · ') }}</span>
            </div>
        @endforeach
    </td></tr>
@endif

@if ($q['coming_up'] !== [])
    <tr><td class="pad" style="padding:10px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:4px;">Coming up</div>
        @foreach ($q['coming_up'] as $line)<div style="font-size:14px;line-height:1.6;color:#d1d5db;">&bull; {{ $line }}</div>@endforeach
    </td></tr>
@endif
