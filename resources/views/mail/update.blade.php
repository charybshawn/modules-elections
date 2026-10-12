@php
    use Illuminate\Support\Str;
    $accent = ['provincial' => '#4338ca', 'municipal' => '#b45309'];
    $since = $p->date($email['since'], 'D, M j \a\t g:i a');
    $count = $email['total'];
    $preheader = collect($email['sections'])->map(fn ($s) => $s['total'].' for '.($s['key'] === 'provincial' ? 'BC provincial' : 'Salmon Arm'))->when(count($email['changelog']) > 0, fn ($c) => $c->push(count($email['changelog']).' site '.Str::plural('change', count($email['changelog']))))->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Election update</title>
<style>
    body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
    a { color: #4338ca; }
    @media only screen and (max-width: 520px) {
        .container { width: 100% !important; }
        .pad { padding-left: 16px !important; padding-right: 16px !important; }
        .stack { display: block !important; width: 100% !important; box-sizing: border-box; padding-left: 0 !important; }
        .stack-gap { padding-top: 10px !important; }
        .party-col { width: 78px !important; }
        .value-col { width: 74px !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background:#eef0f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f4;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" class="container" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px;max-width:640px;">

    {{-- Header: the tracker, the date, and each election's countdown --}}
    <tr><td class="pad" style="background:#111827;border-radius:12px 12px 0 0;padding:26px 28px 22px 28px;">
        <div style="font-size:11px;letter-spacing:2px;font-weight:700;color:#9ca3af;text-transform:uppercase;">Election tracker</div>
        <div style="font-size:26px;line-height:1.2;font-weight:700;color:#ffffff;padding-top:6px;">Election update</div>
        <div style="font-size:13px;color:#d1d5db;padding-top:6px;">{{ $count }} new {{ Str::plural('item', $count) }} since {{ $since }}</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;">
            <tr>
            @foreach ($email['voting_days'] as $i => $day)
                <td class="stack {{ $i > 0 ? 'stack-gap' : '' }}" valign="top" width="{{ floor(100 / max(1, count($email['voting_days']))) }}%" style="{{ $i > 0 ? 'padding-left:10px;' : '' }}">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1f2937;border-radius:8px;border-left:4px solid {{ $accent[$day['key']] === '#4338ca' ? '#818cf8' : '#f59e0b' }};">
                        <tr><td style="padding:10px 14px;">
                            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">{{ $day['label'] }} voting day</div>
                            <div style="font-size:15px;font-weight:700;color:#ffffff;padding-top:2px;">{{ $p->date($day['voting_day'], 'D, M j') }}</div>
                            <div style="font-size:12px;color:#d1d5db;">{{ $p->countdown($day['days_away']) }}</div>
                        </td></tr>
                    </table>
                </td>
            @endforeach
            </tr>
        </table>
    </td></tr>

    {{-- One card per election that has something new --}}
    @foreach ($email['sections'] as $section)
        @php $color = $accent[$section['key']]; @endphp
        <tr><td style="background:#ffffff;border-top:1px solid #e5e7eb;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr><td height="4" style="background:{{ $color }};font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td class="pad" style="padding:22px 28px 6px 28px;">
                    <div style="font-size:11px;letter-spacing:2px;font-weight:700;color:{{ $color }};text-transform:uppercase;">{{ $section['total'] }} new</div>
                    <div style="font-size:20px;font-weight:700;color:#111827;padding-top:2px;">{{ $section['title'] }}</div>
                    @if ($section['subtitle'])<div style="font-size:13px;color:#6b7280;">{{ $section['subtitle'] }}</div>@endif
                </td></tr>
                @if ($section['provincial'])
                    @include('elections::mail.partials.provincial', ['x' => $section['provincial'], 'color' => $color])
                @endif
                @include('elections::mail.partials.digest', ['d' => $section['digest'], 'color' => $color])
                <tr><td class="pad" style="padding:8px 28px 26px 28px;">
                    <a href="{{ $section['url'] }}" style="display:inline-block;background:{{ $color }};color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:10px 18px;border-radius:8px;">Open the {{ $section['key'] === 'provincial' ? 'BC provincial' : 'Salmon Arm' }} tracker &rarr;</a>
                </td></tr>
            </table>
        </td></tr>
    @endforeach

    {{-- What changed on the site itself --}}
    @if (count($email['changelog']) > 0)
        <tr><td style="background:#ffffff;border-top:1px solid #e5e7eb;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr><td height="4" style="background:#0f766e;font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td class="pad" style="padding:22px 28px 6px 28px;">
                    <div style="font-size:11px;letter-spacing:2px;font-weight:700;color:#0f766e;text-transform:uppercase;">{{ count($email['changelog']) }} {{ Str::plural('change', count($email['changelog'])) }}</div>
                    <div style="font-size:20px;font-weight:700;color:#111827;padding-top:2px;">What's new on the site</div>
                </td></tr>
                <tr><td class="pad" style="padding:6px 28px 18px 28px;">
                    @foreach ($email['changelog'] as $entry)
                        <div style="padding:8px 0;{{ ! $loop->last ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                            <div style="font-size:14px;font-weight:700;color:#111827;">{{ $entry['title'] }} <span style="display:inline-block;background:#f3f4f6;color:#4b5563;font-size:11px;font-weight:600;padding:1px 7px;border-radius:10px;vertical-align:1px;">{{ $entry['page_label'] }}</span></div>
                            <div style="font-size:14px;line-height:1.5;color:#374151;">{{ $entry['body'] }}</div>
                        </div>
                    @endforeach
                    <div style="padding-top:2px;font-size:11px;color:#9ca3af;">Biggest effect on the experience first.</div>
                    <div style="padding-top:8px;font-size:13px;"><a href="{{ route('admin.elections.changelog') }}" style="color:#0f766e;font-weight:600;">See the full change log &rarr;</a></div>
                </td></tr>
            </table>
        </td></tr>
    @endif

    {{-- Footer --}}
    <tr><td class="pad" style="background:#ffffff;border-top:1px solid #e5e7eb;border-radius:0 0 12px 12px;padding:18px 28px 22px 28px;">
        <div style="font-size:12px;line-height:1.6;color:#6b7280;">
            You receive this because you have access to the Elections section. <a href="{{ route('admin.elections.home') }}" style="color:#6b7280;">Open the tracker</a>.<br>
            Polls are published by their pollsters and averaged here; riding projections are a model's estimate, not polls of the riding. Times are Pacific.
        </div>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
