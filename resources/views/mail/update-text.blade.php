@php use Illuminate\Support\Str; @endphp
ELECTION UPDATE
{{ $email['total'] }} new {{ Str::plural('item', $email['total']) }} since {{ $p->date($email['since'], 'D, M j \a\t g:i a') }}

@foreach ($email['voting_days'] as $day)
{{ $day['label'] }} voting day: {{ $p->date($day['voting_day'], 'D, M j') }} ({{ $p->countdown($day['days_away']) }})
@endforeach

@foreach ($email['sections'] as $section)
{{ str_repeat('=', 60) }}
{{ strtoupper($section['title']) }}@if ($section['subtitle']) - {{ $section['subtitle'] }}@endif

@if ($section['total'] === 0)
No new updates since the last email.

@if ($section['quiet']['stories'] !== [])
LATEST STORIES
@foreach ($section['quiet']['stories'] as $story)
  {{ $story['title'] }}@if ($story['outlet']) ({{ $story['outlet'] }})@endif

@if ($p->safeUrl($story['url']))  {{ $story['url'] }}
@endif
@endforeach

@endif
@if ($section['quiet']['coming_up'] !== [])
COMING UP
@foreach ($section['quiet']['coming_up'] as $line)
  - {{ $line }}
@endforeach

@endif
Open the tracker: {{ $section['url'] }}

@continue
@endif
@if ($section['provincial'])
@php $x = $section['provincial']; $avg = $x['average']; @endphp
@if ($avg)
THE POLLS (average of {{ $avg['polls'] }} current polls, {{ $p->date($avg['from'], 'M j') }} to {{ $p->date($avg['to'], 'M j') }}; changes are since the last email)
@foreach ($avg['parties'] as $party => $a)
  {{ str_pad($party, 20) }} {{ number_format($a['percent'], 1) }}%@if (($avg['change'][$party] ?? null) !== null) ({{ $p->change($avg['change'][$party]) }})@endif

@endforeach
  Poll history: {{ route('admin.elections.provincial.polls') }}

@endif
@if ($x['projection'])
@php $proj = $x['projection']; @endphp
THIS RIDING ({{ $proj['riding'] }}): {{ collect($proj['shares'])->filter(fn ($s) => $s['percent'] >= 1)->map(fn ($s) => $p->short($s['party']).' '.round($s['percent']))->implode(', ') }}@if ($proj['rating']) - {{ $proj['rating'] }}@endif

  A projection, not a poll of the riding (338Canada's model, updated {{ $p->date($proj['as_of'], 'M j') }}).

@endif
@if ($x['fact_checks'] !== [])
NEW FACT-CHECKS
@foreach ($x['fact_checks'] as $check)
  [{{ $check['rating_label'] }}] {{ $check['party'] }}: {{ Str::limit($check['claim'], 150) }}
  {{ $check['url'] }}
@endforeach
@if ($x['more_fact_checks'] > 0)
  Plus {{ $x['more_fact_checks'] }} more in the tracker.
@endif

@endif
@if ($x['party_news'] !== [])
PARTY NEWS
@foreach ($x['party_news'] as $story)
  {{ $story['title'] }} ({{ $story['party'] }}@if ($story['outlet']), {{ $story['outlet'] }}@endif)
@if ($p->safeUrl($story['url']))  {{ $story['url'] }}
@endif
@endforeach
@if ($x['more_party_news'] > 0)
  Plus {{ $x['more_party_news'] }} more {{ Str::plural('story', $x['more_party_news']) }} in the tracker.
@endif

@endif
@endif
@php $d = $section['digest']; @endphp
@if ($d['news'] !== [])
IN THE NEWS
@foreach ($d['news'] as $story)
  {{ $story['title'] }}@if (! empty($story['outlet'])) ({{ $story['outlet'] }})@endif{{ $story['text'] !== '' ? ' - '.$story['text'] : '' }}
@if ($p->safeUrl($story['url']))  {{ $story['url'] }}
@endif
@endforeach
@if ($d['more_news'] > 0)
  Plus {{ $d['more_news'] }} more {{ Str::plural('story', $d['more_news']) }} in the tracker.
@endif

@endif
@foreach ($d['shared'] as $item)
ONE QUESTION, MANY ANSWERS: {{ $item['title'] }}@if ($item['outlet']) ({{ $item['outlet'] }})@endif

  {{ $item['text'] }}

@endforeach
@if ($d['coming_up'] !== [])
COMING UP
@foreach ($d['coming_up'] as $line)
  - {{ $line }}
@endforeach

@endif
@if ($d['highlights'] !== [])
FROM THE CANDIDATES
@foreach ($d['highlights'] as $sentence)
  {{ $p->plain($sentence) }}
@endforeach

@endif
@if ($d['also'] !== [])
Also updated: {{ $p->join($d['also']) }}.

@endif
Open the tracker: {{ $section['url'] }}

@endforeach
@if (count($email['changelog']) > 0)
{{ str_repeat('=', 60) }}
WHAT'S NEW ON THE SITE (biggest effect on the experience first)

@foreach ($email['changelog'] as $entry)
  {{ $entry['title'] }} [{{ $entry['page_label'] }}]

    {{ $entry['body'] }}
@endforeach

  Full change log: {{ route('admin.elections.changelog') }}

@endif
{{ str_repeat('-', 60) }}
You receive this because you have access to the Elections section.
Polls are published by their pollsters and averaged here; riding projections are a model's estimate, not polls of the riding. Times are Pacific.
