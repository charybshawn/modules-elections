<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="City grants" :href="route('admin.elections.plans.index')" />

    <div class="px-4 sm:px-0">
      <ElectionsNav class="mb-6 print:hidden" />

      <article class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10 print:shadow-none print:ring-0">
        <header class="bg-gradient-to-br from-sky-600 to-indigo-700 px-6 py-7 sm:px-8 text-white print:bg-none print:text-gray-900 print:px-0">
          <p class="text-xs font-semibold uppercase tracking-widest text-sky-100 print:text-gray-500">City of Salmon Arm</p>
          <h1 class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight">{{ g.title }}</h1>
          <p class="mt-1 text-sm text-sky-50 print:text-gray-600">{{ g.subtitle }}</p>
          <p v-if="wasUpdated" class="mt-3 inline-block rounded-full bg-orange-500 px-3 py-1 text-sm font-semibold text-white print:hidden">Updated since your last visit</p>
          <p class="mt-3 text-xs text-sky-100 print:text-gray-500">From council resolutions, the City's audited grant schedules and funder announcements. Read {{ formatDate(g.read_on) }}.</p>
        </header>

        <div class="px-6 py-6 sm:px-8 print:px-0 space-y-10">
          <!-- Key numbers -->
          <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="stat in g.stats" :key="stat.label" class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-3 ring-1 ring-gray-900/5 dark:ring-white/5">
              <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</dd>
              <dt class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ stat.label }} <Cite :keys="stat.sources" :index="sourceIndex" /></dt>
            </div>
          </dl>

          <!-- Outcomes -->
          <section class="break-inside-avoid">
            <SectionHeading>Applications and what came of them</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ g.ledger.length }} applications council authorized, 2016 to 2026 <Cite :keys="['agendaCenter', 'escribe']" :index="sourceIndex" /></p>
            <div class="mt-4 flex h-8 w-full gap-0.5 overflow-hidden rounded">
              <div
                v-for="o in outcomeCounts"
                :key="o.key"
                :class="['h-full first:rounded-l last:rounded-r', outcomes[o.key].bar]"
                :style="{ width: `${(o.count / g.ledger.length) * 100}%` }"
                :title="`${outcomes[o.key].label}: ${o.count}`"
              />
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
              <li v-for="o in outcomeCounts" :key="o.key" class="flex items-center gap-1.5">
                <span :class="['h-3 w-3 rounded-sm', outcomes[o.key].bar]" aria-hidden="true" />
                <span class="text-gray-800 dark:text-gray-200">{{ outcomes[o.key].icon }} {{ outcomes[o.key].label }}</span>
                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ o.count }}</span>
              </li>
            </ul>

            <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Share funded, where the result is known</h3>
            <div class="mt-3 space-y-3">
              <div v-for="era in g.eras" :key="era.label" class="grid grid-cols-[6rem_1fr_auto] items-center gap-3 text-sm" :title="`${era.label}: ${era.funded} of ${era.decided} funded`">
                <span class="text-gray-800 dark:text-gray-200">{{ era.label }}</span>
                <span class="h-5 rounded-r bg-gray-100 dark:bg-gray-900/50"><span class="block h-5 rounded-r bg-emerald-600 dark:bg-emerald-500" :style="{ width: `${(era.funded / era.decided) * 100}%` }" /></span>
                <span class="w-36 text-right tabular-nums"><span class="font-semibold text-gray-900 dark:text-white">{{ Math.round((era.funded / era.decided) * 100) }}%</span> <span class="text-gray-500 dark:text-gray-400">({{ era.funded }} of {{ era.decided }})</span></span>
              </div>
            </div>
          </section>

          <!-- Big asks -->
          <section class="break-inside-avoid">
            <SectionHeading>The big asks</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Applications for projects of $1 million or more. Most of the City's misses are here.</p>
            <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">
              <li v-for="row in bigAsks" :key="row.year + row.program + row.project" class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:gap-4">
                <span class="w-12 shrink-0 text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ row.year }}</span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ row.project }} <span class="font-normal text-gray-500 dark:text-gray-400">· {{ row.asked }}</span></p>
                  <p class="text-xs text-gray-600 dark:text-gray-400">{{ row.program }}. {{ row.evidence }} <Cite :keys="row.sources" :index="sourceIndex" /></p>
                </div>
                <OutcomeBadge :outcome="row.outcome" :received="row.received" class="shrink-0" />
              </li>
            </ul>
          </section>

          <!-- Money by year -->
          <section class="break-inside-avoid">
            <SectionHeading>Grant money recorded each year</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ money(totals.formula + totals.applied) }} from 2016 to 2025: {{ money(totals.formula) }} by formula, {{ money(totals.applied) }} from applications <Cite :keys="['ar2016', 'ar2022', 'fs2025']" :index="sourceIndex" />
            </p>
            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
              <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-[#2a78d6] dark:bg-[#3987e5]" aria-hidden="true" /><span class="text-gray-800 dark:text-gray-200">Formula money (arrives without applying)</span></span>
              <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-[#eb6834] dark:bg-[#d95926]" aria-hidden="true" /><span class="text-gray-800 dark:text-gray-200">Won through applications</span></span>
            </div>
            <div class="mt-4 overflow-x-auto">
              <div class="flex h-56 min-w-[34rem] items-end gap-2 border-b border-gray-300 dark:border-gray-600">
                <div v-for="y in g.years" :key="y.year" class="flex h-full flex-1 flex-col justify-end gap-0.5" :title="`${y.year}: ${money(y.formula)} formula, ${money(y.applied)} from applications`">
                  <span class="block rounded-t bg-[#eb6834] dark:bg-[#d95926]" :style="{ height: `${(y.applied / maxYear) * 100}%` }" />
                  <span class="block bg-[#2a78d6] dark:bg-[#3987e5]" :style="{ height: `${(y.formula / maxYear) * 100}%` }" />
                </div>
              </div>
              <div class="flex min-w-[34rem] gap-2 pt-1.5">
                <div v-for="y in g.years" :key="y.year" class="flex-1 text-center">
                  <p class="text-xs font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ y.year }}</p>
                  <p class="text-[11px] tabular-nums text-gray-500 dark:text-gray-400">{{ money(y.applied) }}</p>
                </div>
              </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Small figures under each year are the money won through applications. The tall years are formula money: the COVID-19 restart grant (2020), gas tax spending (2022) and the Growing Communities Fund (2023).</p>
          </section>

          <!-- Peers -->
          <section class="break-inside-avoid">
            <SectionHeading>How Salmon Arm compares</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Money from federal, provincial and other governments per resident a year, 2015 to 2024, for stand-alone BC towns of 12,000 to 32,000 people <Cite :keys="['bcStats']" :index="sourceIndex" /></p>
            <div class="mt-4 space-y-2">
              <div
                v-for="p in g.peers"
                :key="p.name"
                class="grid grid-cols-[minmax(0,8.5rem)_1fr_auto] items-center gap-3 text-sm"
                :title="`${p.name}: $${p.per_resident} per resident a year (2015-19: $${p.early}, 2020-24: $${p.late})`"
              >
                <span :class="p.name === 'Salmon Arm' ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">{{ p.name }}</span>
                <span class="relative h-5">
                  <span :class="['block h-5 rounded-r', p.name === 'Salmon Arm' ? 'bg-[#eb6834] dark:bg-[#d95926]' : 'bg-gray-300 dark:bg-gray-600']" :style="{ width: `${(p.per_resident / maxPeer) * 100}%` }" />
                  <span class="absolute inset-y-[-3px] w-0.5 bg-gray-900 dark:bg-white" :style="{ left: `${(peerMedian / maxPeer) * 100}%` }" aria-hidden="true" />
                </span>
                <span :class="['w-12 text-right tabular-nums', p.name === 'Salmon Arm' ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300']">${{ p.per_resident }}</span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 sm:pl-[9.25rem]">The black line marks the group median, ${{ peerMedian }}.</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div v-for="grp in g.groups" :key="grp.label" class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-4 ring-1 ring-gray-900/5 dark:ring-white/5">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ grp.label }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ ordinal(grp.rank) }} of {{ grp.of }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">Median ${{ grp.median }}; Salmon Arm ${{ salmonArm }}</p>
              </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">The gap is closing</h3>
            <div class="mt-3 space-y-3">
              <div v-for="half in halves" :key="half.label" class="grid grid-cols-[6rem_1fr] items-start gap-3 text-sm">
                <span class="pt-0.5 text-gray-800 dark:text-gray-200">{{ half.label }}</span>
                <div class="space-y-1">
                  <div class="grid grid-cols-[1fr_auto] items-center gap-2" :title="`Salmon Arm ${half.label}: $${half.salmon_arm}`">
                    <span class="h-4"><span class="block h-4 rounded-r bg-[#eb6834] dark:bg-[#d95926]" :style="{ width: `${(half.salmon_arm / maxHalf) * 100}%` }" /></span>
                    <span class="w-40 text-right tabular-nums text-gray-900 dark:text-white">Salmon Arm ${{ half.salmon_arm }}</span>
                  </div>
                  <div class="grid grid-cols-[1fr_auto] items-center gap-2" :title="`Median ${half.label}: $${half.median}`">
                    <span class="h-4"><span class="block h-4 rounded-r bg-gray-300 dark:bg-gray-600" :style="{ width: `${(half.median / maxHalf) * 100}%` }" /></span>
                    <span class="w-40 text-right tabular-nums text-gray-600 dark:text-gray-300">Median ${{ half.median }} · {{ Math.round((half.salmon_arm / half.median) * 100) }}%</span>
                  </div>
                </div>
              </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
              Left out of the stand-alone group: <template v-for="(e, i) in g.excluded" :key="e.name">{{ i ? '; ' : '' }}{{ e.name }} ({{ e.reason }})</template>. Per resident uses the 2021 census.
            </p>
          </section>

          <!-- Powell River -->
          <section class="break-inside-avoid">
            <SectionHeading>Why Powell River is so far ahead</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ g.powell_river.intro }}</p>
            <div class="mt-4 space-y-4">
              <div v-for="(pt, i) in g.powell_river.points" :key="pt.heading" class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-semibold text-white">{{ i + 1 }}</span>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ pt.heading }}</h3>
                  <p class="mt-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ pt.text }} <Cite :keys="pt.sources" :index="sourceIndex" /></p>
                </div>
              </div>
            </div>
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ g.powell_river.unknown }}</p>
          </section>

          <!-- Full ledger -->
          <section>
            <SectionHeading>Every application</SectionHeading>
            <div class="mt-3 flex flex-wrap gap-2 print:hidden" role="group" aria-label="Filter by result">
              <button
                v-for="f in filters"
                :key="f.key"
                type="button"
                :aria-pressed="filter === f.key"
                :class="['tap-target-touch rounded-full px-3 py-1 text-sm font-medium ring-1 transition', filter === f.key ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700']"
                @click="filter = f.key"
              >{{ f.label }} <span class="tabular-nums opacity-75">{{ f.count }}</span></button>
            </div>
            <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">
              <li v-for="row in visibleRows" :key="row.year + row.program + row.project" class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[3rem_minmax(0,1fr)_auto] sm:gap-4">
                <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ row.year }}</span>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ row.project }}<span v-if="row.asked" class="font-normal text-gray-500 dark:text-gray-400"> · {{ row.asked }}</span></p>
                  <p class="text-xs text-gray-600 dark:text-gray-400">{{ row.program }}</p>
                  <p class="mt-0.5 text-xs text-gray-700 dark:text-gray-300">{{ row.evidence }} <Cite :keys="row.sources" :index="sourceIndex" /></p>
                </div>
                <OutcomeBadge :outcome="row.outcome" :received="row.received" class="sm:justify-self-end" />
              </li>
            </ul>
          </section>

          <!-- Analysis -->
          <section class="break-inside-avoid rounded-xl ring-1 ring-indigo-200 dark:ring-indigo-400/30 bg-indigo-50/50 dark:bg-indigo-500/5 p-5">
            <div class="flex flex-wrap items-center gap-2">
              <SectionHeading>What it adds up to</SectionHeading>
              <span class="rounded-full bg-indigo-100 dark:bg-indigo-500/20 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">AI analysis</span>
            </div>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">An interpretation built only on the figures above, not a verdict on any candidate.</p>
            <div class="mt-4 space-y-4">
              <div v-for="(a, i) in g.analysis" :key="a.heading" class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">{{ i + 1 }}</span>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ a.heading }}</h3>
                  <p class="mt-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ a.text }} <Cite :keys="a.sources" :index="sourceIndex" /></p>
                </div>
              </div>
            </div>
            <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">
              What borrowing for the sewage plant could mean for taxes: see <Link :href="route('admin.elections.finances')" class="text-indigo-600 dark:text-indigo-400 hover:underline">City finances</Link>.
            </p>
            <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Questions worth asking the candidates</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-800 dark:text-gray-200">
              <li v-for="q in g.questions" :key="q">{{ q }}</li>
            </ul>
          </section>

          <!-- Method -->
          <section class="break-inside-avoid">
            <SectionHeading>How this was put together</SectionHeading>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
              <li v-for="m in g.method" :key="m">{{ m }}</li>
            </ul>
          </section>

          <!-- Sources -->
          <section class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-4 ring-1 ring-gray-900/5 dark:ring-white/5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Sources</h2>
            <ol class="mt-2 space-y-1 text-sm">
              <li v-for="(key, i) in sourceOrder" :id="`source-${i + 1}`" :key="key" class="flex gap-2">
                <span class="w-6 shrink-0 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ i + 1 }}.</span>
                <span><a :href="g.sources[key].url" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ g.sources[key].label }}</a><span v-if="g.sources[key].date" class="text-gray-500 dark:text-gray-400"> · {{ formatDate(g.sources[key].date) }}</span></span>
              </li>
            </ol>
            <p v-for="gap in g.gaps" :key="gap" class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ gap }}</p>
          </section>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { formatDate } from '../Partials/format'
import { useUpdates } from '../Partials/updates'

// Opening the page marks it seen; the "Updated" ribbon stays for this visit only.
const updates = useUpdates()
const wasUpdated = ref(false)
onMounted(async () => {
  await updates.ensure()
  wasUpdated.value = updates.grantsUnread()
  updates.mark('plans:city-grants')
})

defineOptions({ layout: (hh, page) => hh(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

type Source = { label: string; url: string; date: string | null }
type Cited = { sources: string[] }
type Outcome = 'funded' | 'not_funded' | 'no_record' | 'pending' | 'unknown' | 'not_submitted'
type Row = { year: number; program: string; project: string; asked: string; outcome: Outcome; received: string; evidence: string } & Cited

const props = defineProps<{
  grants: {
    title: string
    subtitle: string
    read_on: string
    sources: Record<string, Source>
    stats: ({ value: string; label: string } & Cited)[]
    ledger: Row[]
    eras: { label: string; decided: number; funded: number }[]
    years: { year: number; formula: number; applied: number }[]
    peers: { name: string; population: number; per_resident: number; early: number; late: number }[]
    groups: { label: string; median: number; rank: number; of: number }[]
    halves: { early: { salmon_arm: number; median: number }; late: { salmon_arm: number; median: number } }
    excluded: { name: string; reason: string }[]
    powell_river: { intro: string; points: ({ heading: string; text: string } & Cited)[]; unknown: string }
    analysis: ({ heading: string; text: string } & Cited)[]
    questions: string[]
    method: string[]
    gaps: string[]
  }
}>()

const g = computed(() => props.grants)

// Status colours, always shown with an icon and a label.
const outcomes: Record<Outcome, { label: string; icon: string; bar: string; badge: string }> = {
  funded: { label: 'Funded', icon: '✓', bar: 'bg-emerald-600 dark:bg-emerald-500', badge: 'bg-emerald-50 text-emerald-800 ring-emerald-600/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30' },
  not_funded: { label: 'Not funded', icon: '✕', bar: 'bg-[#d03b3b]', badge: 'bg-red-50 text-red-800 ring-red-600/30 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/30' },
  no_record: { label: 'No record of funding', icon: '–', bar: 'bg-red-200 dark:bg-red-900', badge: 'bg-red-50/60 text-red-700 ring-red-300 dark:bg-red-500/5 dark:text-red-300 dark:ring-red-400/20' },
  pending: { label: 'Pending', icon: '⏳', bar: 'bg-[#eda100] dark:bg-[#c98500]', badge: 'bg-amber-50 text-amber-800 ring-amber-600/30 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30' },
  unknown: { label: 'Result unknown', icon: '?', bar: 'bg-gray-300 dark:bg-gray-600', badge: 'bg-gray-50 text-gray-700 ring-gray-300 dark:bg-gray-700/50 dark:text-gray-300 dark:ring-gray-600' },
  not_submitted: { label: 'Not submitted', icon: '○', bar: 'bg-gray-500 dark:bg-gray-400', badge: 'bg-gray-50 text-gray-600 ring-gray-300 dark:bg-gray-700/50 dark:text-gray-400 dark:ring-gray-600' },
}
const order: Outcome[] = ['funded', 'not_funded', 'no_record', 'pending', 'unknown', 'not_submitted']

const outcomeCounts = computed(() => order.map((key) => ({ key, count: g.value.ledger.filter((r) => r.outcome === key).length })).filter((o) => o.count > 0))

// Projects or asks of $1 million or more, read from the "asked" text ("$1.5M project").
const bigAsks = computed(() => g.value.ledger.filter((r) => /\$[\d.]+M/.test(r.asked)))

const filter = ref<'all' | Outcome>('all')
const filters = computed(() => [
  { key: 'all' as const, label: 'All', count: g.value.ledger.length },
  ...outcomeCounts.value.map((o) => ({ key: o.key, label: outcomes[o.key].label, count: o.count })),
])
const visibleRows = computed(() => (filter.value === 'all' ? g.value.ledger : g.value.ledger.filter((r) => r.outcome === filter.value)))

const totals = computed(() => g.value.years.reduce((t, y) => ({ formula: t.formula + y.formula, applied: t.applied + y.applied }), { formula: 0, applied: 0 }))
const maxYear = computed(() => Math.max(...g.value.years.map((y) => y.formula + y.applied)))

const salmonArm = computed(() => g.value.peers.find((p) => p.name === 'Salmon Arm')?.per_resident ?? 0)
const maxPeer = computed(() => Math.max(...g.value.peers.map((p) => p.per_resident)))
const peerMedian = computed(() => {
  const v = g.value.peers.map((p) => p.per_resident).sort((a, b) => a - b)
  const m = Math.floor(v.length / 2)
  return v.length % 2 ? v[m] : Math.round((v[m - 1] + v[m]) / 2)
})
const halves = computed(() => [
  { label: '2015–2019', ...g.value.halves.early },
  { label: '2020–2024', ...g.value.halves.late },
])
const maxHalf = computed(() => Math.max(...halves.value.flatMap((x) => [x.salmon_arm, x.median])))

// Number sources in the order they first appear on the page.
const sourceOrder = computed(() => {
  const seen: string[] = []
  const add = (keys: string[]) => keys.forEach((k) => { if (!seen.includes(k)) seen.push(k) })
  const x = g.value
  x.stats.forEach((s) => add(s.sources))
  add(['agendaCenter', 'escribe'])
  bigAsks.value.forEach((r) => add(r.sources))
  add(['ar2016', 'ar2022', 'fs2025', 'bcStats'])
  x.powell_river.points.forEach((p) => add(p.sources))
  x.ledger.forEach((r) => add(r.sources))
  x.analysis.forEach((s) => add(s.sources))
  Object.keys(x.sources).forEach((k) => add([k]))
  return seen
})
const sourceIndex = computed(() => Object.fromEntries(sourceOrder.value.map((k, i) => [k, i + 1])))

const money = (n: number) => (n >= 1e6 ? `$${(n / 1e6).toFixed(1)}M` : `$${Math.round(n / 1000)}K`)
const ordinal = (n: number) => `${n}${['th', 'st', 'nd', 'rd'][(n % 100 >= 11 && n % 100 <= 13) || n % 10 > 3 ? 0 : n % 10]}`

const SectionHeading = defineComponent({
  setup: (_, { slots }) => () =>
    h('h2', { class: 'flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300' }, [
      h('span', { class: 'h-4 w-1 rounded-full bg-sky-500', 'aria-hidden': 'true' }),
      slots.default?.(),
    ]),
})

/** Superscript source numbers, each jumping to the Sources list. */
const Cite = defineComponent({
  props: { keys: { type: Array as () => string[], required: true }, index: { type: Object as () => Record<string, number>, required: true } },
  setup: (p) => () =>
    h('sup', { class: 'whitespace-nowrap text-[10px] font-medium' }, p.keys.map((k, i) => [
      i ? h('span', { class: 'text-gray-400' }, ',') : null,
      h('a', { href: `#source-${p.index[k]}`, class: 'text-indigo-500 hover:underline dark:text-indigo-400', title: 'See source' }, `[${p.index[k]}]`),
    ])),
})

/** The result of one application: icon, label and, when funded, the amount. */
const OutcomeBadge = defineComponent({
  props: { outcome: { type: String as () => Outcome, required: true }, received: { type: String, default: '' } },
  setup: (p) => () =>
    h('span', { class: ['inline-flex h-fit w-fit items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset', outcomes[p.outcome].badge] }, [
      h('span', { 'aria-hidden': 'true' }, outcomes[p.outcome].icon),
      outcomes[p.outcome].label,
      p.outcome === 'funded' && p.received ? h('span', { class: 'font-medium opacity-80' }, `· ${p.received}`) : null,
    ]),
})
</script>
