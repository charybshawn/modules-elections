{{-- What only the provincial election has: the polls, this riding's projection, fact-checks and party news. --}}
@php
    use Illuminate\Support\Str;
    $avg = $x['average'];
    $scale = $avg ? max(1, collect($avg['parties'])->max('percent')) * 1.1 : 1;
    $chip = fn (string $party, $value) => '<span style="display:inline-block;background:'.$p->colour($party).';color:#ffffff;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;margin:0 3px 3px 0;">'.e($p->short($party)).' '.e(rtrim(rtrim(number_format((float) $value, 1), '0'), '.')).'</span>';
@endphp

@if ($avg)
    <tr><td class="pad" style="padding:14px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;">The polls</div>
        <div style="font-size:12px;color:#9ca3af;padding:2px 0 8px 0;">Average of {{ $avg['polls'] }} current polls, {{ $p->date($avg['from'], 'M j') }} to {{ $p->date($avg['to'], 'M j') }} · changes are since the last email</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            @foreach ($avg['parties'] as $party => $a)
                @php $change = $avg['change'][$party] ?? null; @endphp
                <tr>
                    <td class="party-col" width="120" style="font-size:13px;font-weight:600;color:#f3f4f6;padding:3px 8px 3px 0;white-space:nowrap;">{{ Str::limit($party, 18, '') }}</td>
                    <td style="padding:3px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1f2937;border-radius:4px;">
                            <tr><td width="{{ max(1, round($a['percent'] / $scale * 100)) }}%" height="12" style="background:{{ $p->colour($party) }};border-radius:4px;font-size:0;line-height:0;">&nbsp;</td><td style="font-size:0;line-height:0;">&nbsp;</td></tr>
                        </table>
                    </td>
                    <td class="value-col" width="96" align="right" style="font-size:13px;color:#f3f4f6;padding:3px 0 3px 8px;white-space:nowrap;"><strong>{{ number_format($a['percent'], 1) }}%</strong>@if ($change !== null) <span style="font-size:11px;color:#9ca3af;">{{ $p->change($change) }}</span>@endif</td>
                </tr>
            @endforeach
        </table>
        <div style="font-size:13px;padding-top:8px;"><a href="{{ route('admin.elections.provincial.polls') }}" style="color:{{ $tint }};font-weight:600;">Poll history and trends &rarr;</a></div>
    </td></tr>
@endif

@if ($x['projection'])
    @php $proj = $x['projection']; @endphp
    <tr><td class="pad" style="padding:16px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;">This riding &middot; {{ $proj['riding'] }}</div>
        <div style="padding-top:6px;">
            @foreach ($proj['shares'] as $share)@if ($share['percent'] >= 1){!! $chip($share['party'], round($share['percent'])) !!}@endif @endforeach
            @if ($proj['rating'])<span style="font-size:12px;font-weight:600;color:#d1d5db;text-transform:capitalize;"> &middot; {{ $proj['rating'] }}</span>@endif
        </div>
        <div style="font-size:12px;line-height:1.5;color:#9ca3af;padding-top:4px;">
            A projection, not a poll of the riding: <a href="{{ $p->safeUrl($proj['url']) ?? '#' }}" style="color:#9ca3af;">338Canada's model</a>, updated {{ $p->date($proj['as_of'], 'M j') }}.
            @if (! empty($proj['leaders']))Across B.C. it has {{ $p->join(collect($proj['leaders'])->map(fn ($n, $party) => $party.' leading '.$n)->values()->all()) }} {{ array_sum($proj['leaders']) === 1 ? 'riding' : 'ridings' }}.@endif
        </div>
    </td></tr>
@endif

@if ($x['fact_checks'] !== [])
    <tr><td class="pad" style="padding:16px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:6px;">New fact-checks</div>
        @foreach ($x['fact_checks'] as $check)
            @php [$fg, $bg] = $p->ratingColours($check['rating']); @endphp
            <div style="padding:6px 0;{{ ! $loop->last ? 'border-bottom:1px solid #1f2937;' : '' }}">
                <span style="display:inline-block;background:{{ $bg }};color:{{ $fg }};font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px;">{{ $check['rating_label'] }}</span>
                <span style="display:inline-block;background:{{ $p->colour($check['party']) }};color:#ffffff;font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px;">{{ $check['party'] }}</span>
                <div style="font-size:14px;line-height:1.5;"><a href="{{ $check['url'] }}" style="color:#f3f4f6;text-decoration:none;">{{ Str::limit($check['claim'], 220, '...', true) }}</a></div>
            </div>
        @endforeach
        @if ($x['more_fact_checks'] > 0)<div style="font-size:13px;color:#9ca3af;padding-top:6px;">Plus {{ $x['more_fact_checks'] }} more in the tracker.</div>@endif
    </td></tr>
@endif

@if ($x['party_news'] !== [])
    <tr><td class="pad" style="padding:16px 20px 4px 20px;">
        <div style="font-size:11px;letter-spacing:1.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;padding-bottom:6px;">Party news</div>
        @foreach ($x['party_news'] as $story)
            <div style="padding:6px 0;{{ ! $loop->last ? 'border-bottom:1px solid #1f2937;' : '' }}">
                @if ($p->safeUrl($story['url']))<a href="{{ $story['url'] }}" style="font-size:15px;font-weight:600;color:#f3f4f6;text-decoration:none;">{{ $story['title'] }}</a>@else<span style="font-size:15px;font-weight:600;color:#f3f4f6;">{{ $story['title'] }}</span>@endif
                <span style="display:inline-block;background:{{ $p->colour($story['party']) }};color:#ffffff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:4px;vertical-align:1px;">{{ $story['party'] }}</span>@if ($story['outlet'])<span style="font-size:12px;color:#9ca3af;"> · {{ $story['outlet'] }}</span>@endif
                @if ($story['text'])<div style="font-size:14px;line-height:1.5;color:#9ca3af;">{{ Str::limit($story['text'], 340, '...', true) }}</div>@endif
            </div>
        @endforeach
        @if ($x['more_party_news'] > 0)<div style="font-size:13px;color:#9ca3af;padding-top:6px;">Plus {{ $x['more_party_news'] }} more {{ Str::plural('story', $x['more_party_news']) }} in the tracker.</div>@endif
    </td></tr>
@endif
